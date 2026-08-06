<?php

namespace App\Http\Requests\Admin;

use App\Models\Room;
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
            $upper = mb_strtoupper(trim($roomType), 'UTF-8');
            $roomType = match (true) {
                str_starts_with($upper, '2D') => '2D',
                str_starts_with($upper, '3D') => '3D',
                str_contains($upper, 'IMAX') => 'IMAX',
                default => trim($roomType),
            };
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
            'room_type' => ['required', Rule::in(['2D', '3D', 'IMAX'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'cleaning_buffer_minutes' => ['nullable', 'integer', 'between:0,180'],
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
        ];
    }
}
