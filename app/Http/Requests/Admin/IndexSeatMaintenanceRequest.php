<?php

namespace App\Http\Requests\Admin;

use App\Models\Seat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexSeatMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seat_code' => ['nullable', 'string', 'max:20'],
            'row' => ['nullable', 'string', 'max:5', 'alpha:ascii'],
            'type' => ['nullable', Rule::in(Seat::TYPES)],
            'status' => ['nullable', Rule::in([...Seat::OPERATIONAL_STATUSES, Seat::STATUS_RETIRED])],
            'couple' => ['nullable', Rule::in(['yes', 'no'])],
            'active_hold' => ['nullable', Rule::in(['yes', 'no'])],
            'future_ticket' => ['nullable', Rule::in(['yes', 'no'])],
            'sort' => ['nullable', Rule::in(['seat_code', 'row', 'type', 'status', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50])],
        ];
    }
}
