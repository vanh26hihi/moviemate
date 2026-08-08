<?php

namespace App\Http\Requests\Admin;

use App\Models\Room;
use App\Models\RoomType;
use App\Services\CinemaAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                Rule::unique('rooms', 'code')->where('cinema_id', $cinemaId ?? 0)
                    ->ignore($room instanceof Room ? $room->id : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'room_type' => ['required', Rule::exists('room_types', 'code')->where(
                fn ($query) => $query->where('is_active', true)
                    ->when($currentRoomType, fn ($query, string $code) => $query->orWhere('code', $code))
            )],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'cleaning_buffer_minutes' => ['nullable', 'integer', 'between:0,180'],
            'template_id' => [$room instanceof Room ? 'prohibited' : 'nullable', 'integer', Rule::exists('room_layout_templates', 'id')->where('status', 'active')],
            'layout_name' => ['nullable', 'required_with:template_id', 'string', 'min:5', 'max:255'],
            'change_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'mã phòng',
            'name' => 'tên phòng',
            'room_type' => 'loại phòng',
            'status' => 'trạng thái',
            'cleaning_buffer_minutes' => 'thời gian vệ sinh phòng',
            'template_id' => 'mẫu sơ đồ phòng',
            'layout_name' => 'tên phiên bản sơ đồ',
        ];
    }
}
