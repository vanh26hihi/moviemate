<?php

namespace App\Http\Requests\Admin;

use App\Models\Seat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSeatMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(Seat::OPERATIONAL_STATUSES)]] + $this->structuralProhibitions();
    }

    /** @return array<string, list<string>> */
    private function structuralProhibitions(): array
    {
        return collect([
            'seat_code', 'row', 'number', 'seat_number', 'row_label', 'x_position', 'y_position',
            'type', 'seat_type', 'seat_type_id', 'pair_code', 'pair_position', 'room_id',
            'room_layout_id', 'capacity', 'booking_status', 'price',
        ])->mapWithKeys(fn (string $field): array => [$field => ['prohibited']])->all();
    }
}
