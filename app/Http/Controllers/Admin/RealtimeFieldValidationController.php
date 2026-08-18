<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Genre;
use App\Models\PresentationFormat;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomLayoutTemplate;
use App\Models\RoomType;
use App\Services\Admin\PromotionAdminAccess;
use App\Services\CinemaAccessService;
use App\Support\AdminUniqueRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class RealtimeFieldValidationController extends Controller
{
    public function __construct(
        private readonly CinemaAccessService $cinemas,
        private readonly PromotionAdminAccess $promotions,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rule' => ['required', 'string', Rule::in(AdminUniqueRules::RULES)],
            'value' => ['required', 'string', 'max:255'],
            'record_id' => ['nullable', 'integer', 'min:1'],
            'cinema_id' => ['nullable', 'integer', 'min:1'],
        ]);

        [$value, $attribute, $rules, $message] = $this->definition(
            $request,
            $payload['rule'],
            $payload['value'],
            isset($payload['record_id']) ? (int) $payload['record_id'] : null,
            isset($payload['cinema_id']) ? (int) $payload['cinema_id'] : null,
        );
        $validator = Validator::make(
            ['value' => $value],
            ['value' => $rules],
            ['value.unique' => $message],
            ['value' => $attribute],
        );

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => $validator->errors()->first('value'),
            ], 422);
        }

        return response()->json(['valid' => true]);
    }

    /** @return array{string, string, array<int, mixed>, string} */
    private function definition(Request $request, string $rule, string $value, ?int $recordId, ?int $cinemaId): array
    {
        return match ($rule) {
            AdminUniqueRules::PROMOTION_CODE => $this->promotionCode($request, $value, $recordId),
            AdminUniqueRules::CINEMA_CODE => $this->cinemaCode($request, $value, $recordId),
            AdminUniqueRules::ROOM_CODE => $this->roomCode($request, $value, $recordId, $cinemaId),
            AdminUniqueRules::ROOM_TYPE_CODE => $this->roomTypeCode($request, $value, $recordId),
            AdminUniqueRules::ROOM_TYPE_NAME => $this->roomTypeName($request, $value, $recordId),
            AdminUniqueRules::PRESENTATION_FORMAT_CODE => $this->presentationFormatCode($request, $value, $recordId),
            AdminUniqueRules::PRESENTATION_FORMAT_NAME => $this->presentationFormatName($request, $value, $recordId),
            AdminUniqueRules::LAYOUT_TEMPLATE_CODE => $this->layoutTemplateCode($request, $value, $recordId),
            AdminUniqueRules::GENRE_SLUG => $this->genreSlug($request, $value, $recordId),
        };
    }

    private function promotionCode(Request $request, string $value, ?int $recordId): array
    {
        $this->authorizePermission($request, 'discounts.manage');
        $promotion = $this->record(Promotion::class, $recordId);
        if ($promotion) {
            $this->promotions->authorizeManage($request->user(), $promotion);
        } else {
            abort_if($this->promotions->mutationCinemaIds($request->user())->isEmpty() && ! $this->cinemas->hasGlobalAccess($request->user()), 404);
        }

        return [
            mb_strtoupper(trim($value)),
            'mã khuyến mãi',
            ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', AdminUniqueRules::promotionCode($promotion)],
            'Mã khuyến mãi này đã tồn tại.',
        ];
    }

    private function cinemaCode(Request $request, string $value, ?int $recordId): array
    {
        $this->authorizePermission($request, 'cinemas.manage');
        $cinema = $this->record(Cinema::class, $recordId);
        if ($cinema) {
            $this->cinemas->authorizeCinema($request->user(), (int) $cinema->getKey());
        }

        return [
            mb_strtoupper(trim($value)),
            'mã chi nhánh',
            ['required', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', AdminUniqueRules::cinemaCode($cinema)],
            'Mã chi nhánh này đã tồn tại.',
        ];
    }

    private function roomCode(Request $request, string $value, ?int $recordId, ?int $requestedCinemaId): array
    {
        $room = $this->record(Room::class, $recordId);
        $this->authorizePermission($request, $room ? 'rooms.update' : 'rooms.create');
        if ($room) {
            $cinemaId = (int) $room->cinema_id;
        } else {
            $cinemaId = $this->cinemas->currentCinemaId($request->user());
            if ($cinemaId === null && $this->cinemas->hasGlobalAccess($request->user())) {
                $cinemaId = $requestedCinemaId;
            }
            abort_unless($cinemaId && Cinema::query()->whereKey($cinemaId)->exists(), 404);
        }
        $this->cinemas->authorizeCinema($request->user(), $cinemaId);

        return [
            mb_strtoupper(trim($value)),
            'mã phòng',
            ['required', 'string', 'max:32', AdminUniqueRules::roomCode($cinemaId, $room)],
            'Mã phòng này đã tồn tại trong chi nhánh đã chọn.',
        ];
    }

    private function roomTypeCode(Request $request, string $value, ?int $recordId): array
    {
        $this->authorizePermission($request, 'room_types.manage');
        $roomType = $this->record(RoomType::class, $recordId);

        return [
            RoomType::normalizeCode($value),
            'mã loại phòng',
            ['required', 'string', 'max:40', 'regex:/^[A-Z0-9]+(?:_[A-Z0-9]+)*$/', AdminUniqueRules::roomTypeCode($roomType)],
            'Mã loại phòng này đã tồn tại.',
        ];
    }

    private function roomTypeName(Request $request, string $value, ?int $recordId): array
    {
        $this->authorizePermission($request, 'room_types.manage');
        $roomType = $this->record(RoomType::class, $recordId);

        return [trim($value), 'tên loại phòng', ['required', 'string', 'max:120', AdminUniqueRules::roomTypeName($roomType)], 'Tên loại phòng này đã tồn tại.'];
    }

    private function presentationFormatCode(Request $request, string $value, ?int $recordId): array
    {
        $this->authorizePermission($request, 'presentation_formats.manage');
        $format = $this->record(PresentationFormat::class, $recordId);

        return [
            PresentationFormat::normalizeCode($value),
            'mã định dạng trình chiếu',
            ['required', 'string', 'max:40', 'regex:/^[A-Z0-9]+(?:_[A-Z0-9]+)*$/', AdminUniqueRules::presentationFormatCode($format)],
            'Mã định dạng trình chiếu này đã tồn tại.',
        ];
    }

    private function presentationFormatName(Request $request, string $value, ?int $recordId): array
    {
        $this->authorizePermission($request, 'presentation_formats.manage');
        $format = $this->record(PresentationFormat::class, $recordId);

        return [trim($value), 'tên định dạng trình chiếu', ['required', 'string', 'max:120', AdminUniqueRules::presentationFormatName($format)], 'Tên định dạng trình chiếu này đã tồn tại.'];
    }

    private function layoutTemplateCode(Request $request, string $value, ?int $recordId): array
    {
        $this->authorizePermission($request, 'layout_templates.manage');
        $template = $this->record(RoomLayoutTemplate::class, $recordId);

        return [
            mb_strtoupper(trim($value)),
            'mã mẫu sơ đồ',
            ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', AdminUniqueRules::layoutTemplateCode($template)],
            'Mã mẫu sơ đồ này đã tồn tại.',
        ];
    }

    private function genreSlug(Request $request, string $value, ?int $recordId): array
    {
        $this->authorizePermission($request, $recordId ? 'genres.update' : 'genres.create', true);
        $genre = $this->record(Genre::class, $recordId);

        return [trim($value), 'đường dẫn thể loại', ['required', 'string', 'max:255', AdminUniqueRules::genreSlug($genre)], 'Đường dẫn thể loại này đã tồn tại.'];
    }

    private function authorizePermission(Request $request, string $permission, bool $adminOnly = false): void
    {
        abort_unless($request->user()?->isActive() === true && $request->user()->hasPermission($permission), 403);
        if ($adminOnly) {
            abort_unless($request->user()->hasRole('admin'), 403);
        }
    }

    /** @template TModel of Model
     * @param  class-string<TModel>  $model
     * @return TModel|null
     */
    private function record(string $model, ?int $recordId): ?Model
    {
        return $recordId === null ? null : $model::query()->findOrFail($recordId);
    }
}
