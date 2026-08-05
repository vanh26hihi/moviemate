<?php

namespace App\Http\Requests\Admin;

use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only([
            'booking_code', 'customer_name', 'customer_email', 'sort', 'direction',
        ]))->map(fn ($value) => is_string($value) ? trim($value) : $value)->all());
    }

    public function rules(): array
    {
        return [
            'booking_code' => ['nullable', 'string', 'max:60'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_email' => ['nullable', 'string', 'max:191'],
            'movie_id' => ['nullable', 'integer', 'min:1'],
            'room_id' => ['nullable', 'integer', 'min:1'],
            'show_date' => ['nullable', 'date_format:Y-m-d'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'booking_status' => ['nullable', Rule::in(Booking::STATUSES)],
            'payment_status' => ['nullable', Rule::in(Booking::PAYMENT_STATUSES)],
            'ticket_status' => ['nullable', Rule::in(BookingTicketDelivery::STATUSES)],
            'checkin_status' => ['nullable', Rule::in(['used', 'not_used'])],
            'sort' => ['nullable', Rule::in(['created_at', 'booking_code', 'total_amount', 'show_date'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50])],
        ];
    }
}
