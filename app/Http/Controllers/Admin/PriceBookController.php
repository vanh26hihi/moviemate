<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PriceBookException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CopyPriceBookVersionRequest;
use App\Http\Requests\Admin\PreviewPriceBookRequest;
use App\Http\Requests\Admin\PriceBookAdjustmentRequest;
use App\Http\Requests\Admin\UpdatePriceBookVersionRequest;
use App\Models\Cinema;
use App\Models\PriceBookAdjustment;
use App\Models\PriceBookVersion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SeatType;
use App\Services\Admin\PriceBookAdminAccess;
use App\Services\PriceBookVersionService;
use App\Services\VersionedTicketPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class PriceBookController extends Controller
{
    public function __construct(
        private readonly PriceBookAdminAccess $access,
        private readonly PriceBookVersionService $versions,
        private readonly VersionedTicketPricingService $pricing,
    ) {}

    public function index(Request $request): View
    {
        $this->access->authorizeView($request->user());
        $book = $this->versions->chainPriceBook();
        $versions = $this->access->visibleVersions(
            PriceBookVersion::query()->where('price_book_id', $book->id),
            $request->user(),
        )->withCount([
            'adjustments',
            'adjustments as contextual_adjustments_count' => fn ($query) => $query->where('dimension', '!=', 'seat_type'),
        ])->orderByDesc('version_number')->get();

        return view('admin.price-books.index', [
            'priceBook' => $book,
            'versions' => $versions,
            'preview' => null,
            ...$this->contextData($request),
        ]);
    }

    public function show(Request $request, PriceBookVersion $version): View
    {
        return $this->detailView($request, $version);
    }

    public function copy(CopyPriceBookVersionRequest $request, PriceBookVersion $version): RedirectResponse
    {
        $this->access->authorizeManage($request->user());
        $this->assertChainVersion($version);
        abort_unless($version->status === PriceBookVersion::STATUS_PUBLISHED, 403);
        try {
            $draft = $this->versions->copyToDraft(
                $version,
                $request->validated('effective_from'),
                $request->validated('effective_until'),
                $request->user(),
            );
        } catch (PriceBookException $exception) {
            return back()->withErrors(['version' => $this->message($exception)])->withInput();
        }

        return redirect()->route('admin.price-books.versions.show', $draft)
            ->with('success', 'Đã sao chép độc lập sang bản nháp mới; phiên bản nguồn không thay đổi.');
    }

    public function update(UpdatePriceBookVersionRequest $request, PriceBookVersion $version): RedirectResponse
    {
        $this->access->authorizeManage($request->user());
        $this->assertMutable($version);
        try {
            $this->versions->updateDraft($version, $request->validated(), $request->user());
        } catch (PriceBookException $exception) {
            return back()->withErrors(['version' => $this->message($exception)])->withInput();
        }

        return back()->with('success', 'Đã cập nhật giá cơ sở và thời gian hiệu lực của bản nháp.');
    }

    public function storeAdjustment(PriceBookAdjustmentRequest $request, PriceBookVersion $version): RedirectResponse
    {
        $this->access->authorizeManage($request->user());
        $this->assertMutable($version);
        $definitions = $this->definitions($version->adjustments()->orderBy('id')->get());
        $definitions[] = $request->validated();

        return $this->replaceAdjustments($version, $definitions, 'Đã thêm điều chỉnh giá.');
    }

    public function updateAdjustment(
        PriceBookAdjustmentRequest $request,
        PriceBookVersion $version,
        PriceBookAdjustment $adjustment,
    ): RedirectResponse {
        $this->access->authorizeManage($request->user());
        $this->assertMutable($version);
        $this->assertAdjustment($version, $adjustment);
        $definitions = $version->adjustments()->orderBy('id')->get()
            ->map(fn (PriceBookAdjustment $item): array => $item->is($adjustment)
                ? $request->validated()
                : $this->definition($item))
            ->all();

        return $this->replaceAdjustments($version, $definitions, 'Đã cập nhật điều chỉnh giá.');
    }

    public function destroyAdjustment(
        Request $request,
        PriceBookVersion $version,
        PriceBookAdjustment $adjustment,
    ): RedirectResponse {
        $this->access->authorizeManage($request->user());
        $this->assertMutable($version);
        $this->assertAdjustment($version, $adjustment);
        $definitions = $version->adjustments()->whereKeyNot($adjustment->id)->orderBy('id')->get()
            ->map(fn (PriceBookAdjustment $item): array => $this->definition($item))->all();

        return $this->replaceAdjustments($version, $definitions, 'Đã xóa điều chỉnh giá khỏi bản nháp.');
    }

    public function publish(Request $request, PriceBookVersion $version): RedirectResponse
    {
        $this->access->authorizeManage($request->user());
        $this->assertMutable($version);
        try {
            $this->versions->publish($version, $request->user());
        } catch (PriceBookException $exception) {
            return back()->withErrors(['publish' => $this->message($exception)]);
        }

        return back()->with('success', 'Đã phát hành phiên bản bảng giá; định nghĩa tài chính đã được khóa.');
    }

    public function retire(Request $request, PriceBookVersion $version): RedirectResponse
    {
        $this->access->authorizeManage($request->user());
        $this->assertChainVersion($version);
        try {
            $this->versions->retire($version, $request->user());
        } catch (PriceBookException $exception) {
            return back()->withErrors(['retire' => $this->message($exception)]);
        }

        return back()->with('success', 'Đã ngừng sử dụng phiên bản cho các lần phân giải giá trong tương lai.');
    }

    public function preview(PreviewPriceBookRequest $request): View|RedirectResponse
    {
        $cinema = Cinema::query()->findOrFail($request->validated('cinema_id'));
        $room = Room::query()->with(['cinema', 'roomType'])->findOrFail($request->validated('room_id'));
        $seatType = SeatType::query()->findOrFail($request->validated('seat_type_id'));
        $this->access->authorizePreviewRoom($request->user(), $cinema, $room);
        abort_unless($room->roomType !== null && (bool) $seatType->status, 404);

        try {
            $localStart = CarbonImmutable::parse(
                $request->validated('showtime_local_start'),
                $cinema->timezone ?: config('cinema.timezone'),
            );
            $result = $this->pricing->resolve($cinema, $room, $room->roomType, $seatType, $localStart);
            $version = PriceBookVersion::query()->findOrFail($result->priceBookVersionId);
        } catch (PriceBookException $exception) {
            return back()->withErrors(['preview' => $this->message($exception)])->withInput();
        }

        return $this->detailView($request, $version, [
            'price' => $result,
            'cinema' => $cinema,
            'room' => $room,
            'seatType' => $seatType,
            'localStart' => $localStart,
        ]);
    }

    public function previewRedirect(): RedirectResponse
    {
        return redirect()->route('admin.price-books.index');
    }

    private function detailView(Request $request, PriceBookVersion $version, ?array $preview = null): View
    {
        $this->access->authorizeVersionView($request->user(), $version);
        $this->assertChainVersion($version);
        $adjustments = PriceBookAdjustment::query()
            ->where('price_book_version_id', $version->id)
            ->when(! $this->access->isGlobal($request->user()), function ($query) use ($request): void {
                $cinemaId = $this->access->previewCinemas($request->user())->first()?->id;
                $query->where(function ($scope) use ($cinemaId): void {
                    $scope->whereNotIn('dimension', ['cinema', 'room'])
                        ->orWhere(fn ($cinema) => $cinema->where('dimension', 'cinema')->where('cinema_id', $cinemaId))
                        ->orWhere(fn ($room) => $room->where('dimension', 'room')->whereHas('room', fn ($rooms) => $rooms->where('cinema_id', $cinemaId)));
                });
            })
            ->with(['seatType:id,code,name,is_pair', 'roomType:id,code,name', 'cinema:id,code,name', 'room:id,cinema_id,code,name'])
            ->orderBy('id')->get();
        $version->setRelation('adjustments', $adjustments);

        return view('admin.price-books.show', [
            'priceBook' => $this->versions->chainPriceBook(),
            'version' => $version,
            'preview' => $preview,
            ...$this->contextData($request),
        ]);
    }

    private function contextData(Request $request): array
    {
        $cinemas = $this->access->previewCinemas($request->user());
        $rooms = Room::query()->operational()
            ->whereIn('cinema_id', $cinemas->pluck('id'))
            ->with(['cinema:id,code,name', 'roomType:id,code,name'])
            ->orderBy('cinema_id')->orderBy('code')->get();

        return [
            'canManagePriceBook' => $this->access->canManage($request->user()),
            'previewCinemas' => $cinemas,
            'previewRooms' => $rooms,
            'seatTypes' => SeatType::query()->where('status', true)
                ->orderByRaw("CASE code WHEN 'normal' THEN 1 WHEN 'vip' THEN 2 WHEN 'couple' THEN 3 ELSE 4 END")
                ->orderBy('sort_order')->orderBy('name')->get(),
            'roomTypes' => RoomType::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }

    private function assertChainVersion(PriceBookVersion $version): void
    {
        abort_unless((int) $version->price_book_id === (int) $this->versions->chainPriceBook()->id, 404);
    }

    private function assertMutable(PriceBookVersion $version): void
    {
        $this->assertChainVersion($version);
        abort_unless($version->status === PriceBookVersion::STATUS_DRAFT, 403);
    }

    private function assertAdjustment(PriceBookVersion $version, PriceBookAdjustment $adjustment): void
    {
        abort_unless((int) $adjustment->price_book_version_id === (int) $version->id, 404);
    }

    /** @param Collection<int, PriceBookAdjustment> $adjustments */
    private function definitions(Collection $adjustments): array
    {
        return $adjustments->map(fn (PriceBookAdjustment $item): array => $this->definition($item))->all();
    }

    private function definition(PriceBookAdjustment $adjustment): array
    {
        return $adjustment->only([
            'dimension', 'label', 'amount_vnd', 'seat_type_id', 'room_type_id',
            'cinema_id', 'room_id', 'time_start', 'time_end',
            'holiday_date_from', 'holiday_date_until', 'weekend_days',
        ]);
    }

    private function replaceAdjustments(PriceBookVersion $version, array $definitions, string $success): RedirectResponse
    {
        try {
            $this->versions->replaceAdjustments($version, $definitions);
        } catch (PriceBookException $exception) {
            return back()->withErrors(['adjustment' => $this->message($exception)])->withInput();
        }

        return back()->with('success', $success);
    }

    private function message(PriceBookException $exception): string
    {
        return match ($exception->domainCode) {
            PriceBookException::VERSION_NOT_FOUND => 'Không có phiên bản bảng giá đã phát hành áp dụng cho thời điểm này.',
            PriceBookException::VERSION_OVERLAP => 'Thời gian hiệu lực bị chồng lấn với một phiên bản đã phát hành.',
            PriceBookException::AMBIGUOUS_ADJUSTMENT => str_contains($exception->getMessage(), 'Time-window')
                ? 'Các khung giờ điều chỉnh đang chồng lấn.'
                : (str_contains($exception->getMessage(), 'Holiday')
                    ? 'Các khoảng ngày lễ đang chồng lấn.'
                    : 'Có nhiều điều chỉnh trùng cùng một phạm vi.'),
            PriceBookException::RESULT_NOT_POSITIVE => 'Một ngữ cảnh được hỗ trợ có thể tạo giá vé bằng 0 hoặc âm.',
            PriceBookException::IMMUTABLE => 'Phiên bản đã phát hành hoặc đã ngừng sử dụng là bất biến.',
            PriceBookException::INVALID_TRANSITION => 'Thao tác không phù hợp với trạng thái hiện tại của phiên bản.',
            default => 'Định nghĩa bảng giá chưa hợp lệ. Kiểm tra giá cơ sở, thời gian hiệu lực và các điều chỉnh.',
        };
    }
}
