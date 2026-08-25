<?php

namespace App\Http\Requests\Admin;

use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\CinemaAccessService;
use App\Support\AdminUniqueRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('room') instanceof Room
            ? 'rooms.update'
            : 'rooms.create';

        return $this->user()?->isActive() === true
            && $this->user()->hasPermission('admin.access')
            && $this->user()->hasPermission($permission);
    }

    protected function prepareForValidation(): void
    {
        $roomType = $this->input('room_type');
        if (is_string($roomType)) {
            $roomType = RoomType::normalizeCode($roomType);
        }

        $this->merge([
            'code' => is_string($this->input('code')) ? mb_strtoupper(trim($this->input('code'))) : $this->input('code'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'room_type' => $roomType,
            'width_mm' => $this->metersToMillimeters($this->input('width_m')),
            'length_mm' => $this->metersToMillimeters($this->input('length_m')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $room = $this->route('room');
        $access = app(CinemaAccessService::class);
        $roomCinemaId = $room instanceof Room ? (int) $room->cinema_id : null;
        $currentRoomType = $room instanceof Room ? $room->room_type : null;
        $requestedCinemaId = filter_var($this->input('cinema_id'), FILTER_VALIDATE_INT) ?: null;
        $cinemaId = $roomCinemaId ?? $access->currentCinemaId($this->user()) ?? $requestedCinemaId;

        return [
            'cinema_id' => ['nullable', 'integer', 'exists:cinemas,id'],
            'code' => [
                'required', 'string', 'max:32',
                AdminUniqueRules::roomCode($cinemaId ?? 0, $room instanceof Room ? $room : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'room_type' => ['required', Rule::exists('room_types', 'code')->where(
                fn ($query) => $query->where('is_active', true)
                    ->when($currentRoomType, fn ($query, string $code) => $query->orWhere('code', $code))
            )],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'width_mm' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === 'active' || $this->input('length_mm') !== null),
                'nullable', 'integer', 'min:1', 'max:'.Room::MAX_DIMENSION_MM,
            ],
            'length_mm' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === 'active' || $this->input('width_mm') !== null),
                'nullable', 'integer', 'min:1', 'max:'.Room::MAX_DIMENSION_MM,
            ],
            'cleaning_buffer_minutes' => ['nullable', 'integer', 'between:0,180'],
            'template_id' => [$room instanceof Room ? 'prohibited' : 'nullable', 'integer', Rule::exists('room_layout_templates', 'id')->where('status', 'active')],
            'layout_name' => ['nullable', 'required_with:template_id', 'string', 'min:5', 'max:255'],
            'change_note' => ['nullable', 'string', 'max:2000'],
            'presentation_format_ids' => ['nullable', 'required_if:status,active', 'array', 'min:1'],
            'presentation_format_ids.*' => ['integer', 'distinct', 'exists:presentation_formats,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('presentation_format_ids')
                || $validator->errors()->has('presentation_format_ids.*')) {
                return;
            }

            $ids = collect($this->input('presentation_format_ids', []))
                ->map(fn ($id): int => (int) $id)->unique()->values();
            $room = $this->route('room');
            $currentIds = $room instanceof Room
                ? $room->presentationCapabilities()->pluck('presentation_formats.id')->map(fn ($id): int => (int) $id)
                : collect();
            $newIds = $ids->diff($currentIds);

            if ($newIds->isNotEmpty() && PresentationFormat::query()->whereIn('id', $newIds)->where('is_active', false)->exists()) {
                $validator->errors()->add('presentation_format_ids', 'Không thể thêm khả năng trình chiếu đã lưu trữ.');
            }

            if ($this->input('status') === 'active'
                && ! PresentationFormat::query()->active()->whereIn('id', $ids)->exists()) {
                $validator->errors()->add('presentation_format_ids', 'Phòng đang hoạt động phải có ít nhất một khả năng trình chiếu đang sử dụng.');
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'mã phòng',
            'name' => 'tên phòng',
            'room_type' => 'loại phòng',
            'status' => 'trạng thái',
            'width_mm' => 'chiều rộng phòng',
            'length_mm' => 'chiều dài phòng',
            'cleaning_buffer_minutes' => 'thời gian vệ sinh phòng',
            'template_id' => 'mẫu sơ đồ phòng',
            'layout_name' => 'tên phiên bản sơ đồ',
            'presentation_format_ids' => 'khả năng trình chiếu',
            'presentation_format_ids.*' => 'khả năng trình chiếu',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.unique' => 'Mã phòng này đã tồn tại trong chi nhánh đã chọn.',
        ];
    }

    /** Convert dot-decimal meters to exact millimeters without floating-point arithmetic. */
    private function metersToMillimeters(mixed $value): int|string|null
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $value = is_string($value) || is_numeric($value) ? trim((string) $value) : '';
        if (! preg_match('/^(?:0|[1-9][0-9]*)(?:\.([0-9]{1,3}))?$/', $value, $matches)) {
            return 'invalid';
        }

        $wholeMeters = (int) strstr($value.'.', '.', true);
        $fractionMm = (int) str_pad($matches[1] ?? '', 3, '0');
        if ($wholeMeters > intdiv(Room::MAX_DIMENSION_MM, 1000)) {
            return Room::MAX_DIMENSION_MM + 1;
        }

        $millimeters = ($wholeMeters * 1000) + $fractionMm;

        return $millimeters <= Room::MAX_DIMENSION_MM ? $millimeters : Room::MAX_DIMENSION_MM + 1;
    }
}
