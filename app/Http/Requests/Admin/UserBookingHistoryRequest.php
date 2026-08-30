<?php

namespace App\Http\Requests\Admin;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

final class UserBookingHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $dateTo = ['nullable', 'date_format:Y-m-d'];
        if ($this->filled('date_from')) {
            $dateTo[] = 'after_or_equal:date_from';
        }

        return [
            'booking_search' => ['nullable', 'string', 'max:100'],
            'booking_status' => ['nullable', 'in:'.implode(',', Booking::STATUSES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => $dateTo,
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('booking_search')) {
            $search = trim((string) $this->input('booking_search'));
            $this->merge(['booking_search' => $search !== '' ? $search : null]);
        }
    }
}
