<?php

namespace App\Http\Requests\Admin;

use App\Models\Seat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BulkSeatMaintenanceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $seatIds = $this->input('seat_ids');
        if (! is_array($seatIds)) {
            return;
        }

        $this->merge([
            'seat_ids' => collect($seatIds)
                ->map(fn (mixed $id): mixed => is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : $id)
                ->uniqueStrict()
                ->values()
                ->all(),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'seat_ids' => ['required', 'array', 'min:1', 'max:50'],
            'seat_ids.*' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(Seat::OPERATIONAL_STATUSES)],
        ];

        foreach ([
            'seat_code', 'row', 'number', 'seat_number', 'row_label', 'x_position', 'y_position',
            'type', 'seat_type', 'seat_type_id', 'pair_code', 'pair_position', 'room_id',
            'room_layout_id', 'capacity', 'booking_status', 'price',
        ] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
