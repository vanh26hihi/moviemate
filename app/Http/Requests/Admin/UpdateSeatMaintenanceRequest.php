<?php

namespace App\Http\Requests\Admin;

use App\Models\Seat;
use App\Models\SeatIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSeatMaintenanceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('status') === Seat::STATUS_MAINTENANCE && ! $this->filled('reason')) {
            $this->merge(['reason' => SeatIncident::REASON_MAINTENANCE]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Seat::OPERATIONAL_STATUSES)],
            'reason' => ['nullable', Rule::requiredIf($this->input('status') === Seat::STATUS_MAINTENANCE), Rule::in(SeatIncident::REASONS)],
            'note' => ['nullable', 'string', 'max:500', 'required_if:reason,'.SeatIncident::REASON_OTHER],
        ] + $this->structuralProhibitions();
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
