<?php

namespace App\Http\Requests\Admin;

use App\Models\BookingTicketDelivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexTicketDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_code' => ['nullable', 'string', 'max:60'],
            'recipient' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', Rule::in(BookingTicketDelivery::STATUSES)],
            'attempts_min' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'sent_from' => ['nullable', 'date_format:Y-m-d'],
            'sent_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:sent_from'],
            'has_error' => ['nullable', Rule::in(['yes', 'no'])],
            'retry_due' => ['nullable', Rule::in(['yes', 'no'])],
            'stale_claim' => ['nullable', Rule::in(['yes', 'no'])],
            'sort' => ['nullable', Rule::in(['created_at', 'updated_at', 'attempts', 'available_at', 'sent_at', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50])],
        ];
    }
}
