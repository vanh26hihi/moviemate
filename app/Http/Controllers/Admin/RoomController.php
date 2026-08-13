<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoomRequest;
use App\Models\Cinema;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayoutTemplate;
use App\Models\RoomType;
use App\Services\ActivityLogger;
use App\Services\ApplyRoomLayoutTemplateService;
use App\Services\CinemaAccessService;
use App\Services\RoomPresentationCapabilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly ActivityLogger $activityLogger,
        private readonly ApplyRoomLayoutTemplateService $templateApplicator,
        private readonly RoomPresentationCapabilityService $capabilities,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $roomType = (string) $request->query('room_type', '');

        $query = Room::query()
            ->with(['cinema', 'roomType', 'latestPublishedLayout.cells.seat', 'draftLayout'])
            ->withCount([
                'showtimes',
                'showtimes as upcoming_showtimes_count' => fn (Builder $query) => $this->futureActiveShowtimes($query),
            ]);
        $this->cinemaAccess->scope($query, $request->user(), 'rooms.cinema_id');
        $rooms = $query
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($roomType !== '' && RoomType::query()->where('code', $roomType)->exists(), fn (Builder $query) => $query->where('room_type', $roomType))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        $roomTypes = RoomType::options(includeInactive: true);

        return view('admin.rooms.index', compact('rooms', 'roomTypes', 'search', 'status', 'roomType'));
    }

    public function create(): View
    {
        $cinema = $this->cinemaAccess->currentCinema(auth()->user());
        $cinemas = $this->cinemaAccess->accessibleCinemas(auth()->user());
        $templates = RoomLayoutTemplate::query()->active()->orderBy('name')->get();
        $roomTypes = RoomType::options();
        $presentationFormats = PresentationFormat::query()->active()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.rooms.create', compact('cinema', 'cinemas', 'templates', 'roomTypes', 'presentationFormats'));
    }

    public function store(SaveRoomRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $cinemaId = $this->targetCinemaId($request, $validated);
        $this->ensureOperationalNameIsUnique($validated, cinemaId: $cinemaId);

        $templateId = $validated['template_id'] ?? null;
        $capabilityIds = $validated['presentation_format_ids'] ?? [];
        $layoutName = $validated['layout_name'] ?? null;
        $changeNote = $validated['change_note'] ?? null;
        unset($validated['template_id'], $validated['layout_name'], $validated['change_note'], $validated['presentation_format_ids']);
        $validated['room_type_id'] = RoomType::query()->where('code', $validated['room_type'])->value('id');
        $room = DB::transaction(function () use ($validated, $cinemaId, $templateId, $capabilityIds, $layoutName, $changeNote, $request): Room {
            $room = Room::query()->create([...$validated, 'total_seats' => 0, 'cinema_id' => $cinemaId]);
            $this->capabilities->syncNew($room, $capabilityIds);
            if ($templateId) {
                $template = RoomLayoutTemplate::query()->findOrFail($templateId);
                $this->templateApplicator->apply($room, $template, (string) $layoutName, $changeNote, $request->user(), true);
            }

            return $room;
        });

        if ($room->status === 'active' && ! $templateId) {
            return redirect()->route('admin.rooms.layout.show', $room)
                ->with('success', 'Đã tạo phòng chiếu. Hãy thiết kế và phát hành sơ đồ ghế trước khi tạo suất chiếu.');
        }

        return redirect()->route('admin.rooms.show', $room)
            ->with('success', $templateId ? 'Đã tạo phòng và phát hành sơ đồ độc lập từ mẫu.' : 'Đã tạo phòng chiếu ở trạng thái ngừng hoạt động.');
    }

    public function show(Room $room): View
    {
        $this->assertManagedRoom($room);
        $room->load([
            'cinema',
            'roomType',
            'latestPublishedLayout.cells.seat',
            'draftLayout',
            'layouts' => fn ($query) => $query->orderByDesc('version'),
        ])->loadCount([
            'showtimes',
            'showtimes as upcoming_showtimes_count' => fn (Builder $query) => $this->futureActiveShowtimes($query),
        ]);
        $templates = auth()->user()->hasPermission('room_layouts.apply_template')
            ? RoomLayoutTemplate::query()->active()->orderBy('name')->get()
            : collect();

        return view('admin.rooms.show', compact('room', 'templates'));
    }

    public function applyTemplate(Request $request, Room $room): RedirectResponse
    {
        $this->assertManagedRoom($room);
        $validated = $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('room_layout_templates', 'id')->where('status', 'active')],
            'layout_name' => ['required', 'string', 'min:5', 'max:255'],
            'change_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $template = RoomLayoutTemplate::query()->findOrFail($validated['template_id']);
        $layout = $this->templateApplicator->apply($room, $template, $validated['layout_name'], $validated['change_note'] ?? null, $request->user());

        return redirect()->route('admin.rooms.layout.show', $room)
            ->with('success', "Đã tạo bản nháp {$layout->display_name} từ mẫu. Hãy kiểm tra trước khi phát hành.");
    }

    public function edit(Room $room): View
    {
        $this->assertManagedRoom($room);
        $cinema = $room->cinema;
        $cinemas = collect([$cinema]);
        $roomTypes = RoomType::options($room->room_type);
        $room->load('presentationCapabilities');
        $presentationFormats = PresentationFormat::query()->active()->orderBy('sort_order')->orderBy('name')->get();
        $archivedPresentationFormats = $room->presentationCapabilities->where('is_active', false)->sortBy('sort_order')->values();

        return view('admin.rooms.edit', compact('room', 'cinema', 'cinemas', 'roomTypes', 'presentationFormats', 'archivedPresentationFormats'));
    }

    public function update(SaveRoomRequest $request, Room $room): RedirectResponse
    {
        $this->assertManagedRoom($room);
        $validated = $request->validated();
        $capabilityIds = $validated['presentation_format_ids'] ?? [];
        unset($validated['presentation_format_ids']);
        $validated['room_type_id'] = RoomType::query()->where('code', $validated['room_type'])->value('id');

        DB::transaction(function () use ($room, $validated, $capabilityIds): void {
            $locked = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatusTransitionIsSafe($locked, $validated['status']);
            $this->ensureOperationalNameIsUnique($validated, $locked->id, (int) $locked->cinema_id);
            $beforeStatus = $locked->status;
            $beforeBuffer = $locked->cleaning_buffer_minutes;
            $locked->status = $validated['status'];
            $this->capabilities->syncLocked($locked, $capabilityIds);
            unset($validated['cinema_id']);
            $locked->update($validated);
            if ($beforeStatus !== $locked->status) {
                $this->logStatusChange($locked, $beforeStatus);
            }
            if ($beforeBuffer !== $locked->cleaning_buffer_minutes) {
                $this->activityLogger->log('room.cleaning_buffer_updated', $locked,
                    ['room_id' => $locked->id, 'cleaning_buffer' => $beforeBuffer],
                    ['room_id' => $locked->id, 'cleaning_buffer' => $locked->cleaning_buffer_minutes]);
            }
        });

        return redirect()->route('admin.rooms.show', $room)
            ->with('success', 'Đã cập nhật phòng chiếu. Sơ đồ ghế và lịch sử đặt vé được giữ nguyên.');
    }

    public function updateStatus(Request $request, Room $room): RedirectResponse
    {
        $this->assertManagedRoom($room);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        DB::transaction(function () use ($room, $validated): void {
            $locked = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatusTransitionIsSafe($locked, $validated['status']);
            $this->ensureOperationalNameIsUnique([
                'name' => $locked->name,
                'status' => $validated['status'],
            ], $locked->id, (int) $locked->cinema_id);
            if ($validated['status'] === 'active') {
                $this->capabilities->assertCanActivateLocked($locked);
            }
            $beforeStatus = $locked->status;
            $locked->update(['status' => $validated['status']]);
            if ($beforeStatus !== $locked->status) {
                $this->logStatusChange($locked, $beforeStatus);
            }
        });

        $message = $validated['status'] === 'active'
            ? 'Đã kích hoạt phòng chiếu.'
            : 'Đã ngừng hoạt động phòng chiếu. Sơ đồ ghế và lịch sử được giữ nguyên.';

        return back()->with('success', $message);
    }

    public function destroy(Room $room): never
    {
        $this->assertManagedRoom($room);
        abort(409, 'Không thể xóa phòng. Hãy ngừng hoạt động phòng để bảo toàn ghế, sơ đồ và lịch sử vận hành.');
    }

    private function assertManagedRoom(Room $room): void
    {
        $this->cinemaAccess->authorizeCinema(auth()->user(), (int) $room->cinema_id);
    }

    private function logStatusChange(Room $room, string $beforeStatus): void
    {
        $this->activityLogger->log(
            'room.status_changed',
            $room,
            ['status' => $beforeStatus],
            ['status' => $room->status],
            ['room_id' => $room->id, 'room_code' => $room->code],
        );
    }

    private function ensureStatusTransitionIsSafe(Room $room, string $newStatus): void
    {
        if ($room->status !== 'active' || $newStatus !== 'inactive') {
            return;
        }

        if ($this->futureActiveShowtimes($room->showtimes()->getQuery())->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Không thể ngừng hoạt động phòng đang có suất chiếu sắp tới. Hãy hủy hoặc chuyển các suất chiếu trước.',
            ]);
        }
    }

    private function futureActiveShowtimes(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', 'active')
            ->where(function (Builder $query) use ($now): void {
                $query->whereDate('show_date', '>', $now->toDateString())
                    ->orWhere(function (Builder $query) use ($now): void {
                        $query->whereDate('show_date', $now->toDateString())
                            ->whereTime('show_time', '>=', $now->format('H:i:s'));
                    });
            });
    }

    /** @param array{name: string, status: string} $data */
    private function ensureOperationalNameIsUnique(array $data, ?int $exceptId = null, ?int $cinemaId = null): void
    {
        if ($data['status'] !== 'active') {
            return;
        }

        $normalizedName = mb_strtolower(trim($data['name']));
        $cinemaId ??= $this->cinemaAccess->currentCinemaId(auth()->user());
        $exists = Room::query()->where('cinema_id', $cinemaId)
            ->where('status', 'active')
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->pluck('name')->contains(fn ($name) => mb_strtolower(trim($name)) === $normalizedName);

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Tên phòng đang hoạt động không được trùng trong cùng cơ sở.',
            ]);
        }
    }

    private function targetCinemaId(Request $request, array $validated): int
    {
        $cinemaId = $this->cinemaAccess->currentCinemaId($request->user())
            ?? (isset($validated['cinema_id']) ? (int) $validated['cinema_id'] : null)
            ?? Cinema::query()->active()->primary()->value('id');
        abort_unless($cinemaId && $this->cinemaAccess->canAccessCinema($request->user(), $cinemaId), 403);

        return (int) $cinemaId;
    }
}
