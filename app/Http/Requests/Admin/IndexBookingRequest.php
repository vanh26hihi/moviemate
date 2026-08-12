<?php

namespace App\Http\Requests\Admin;

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
            'search', 'sort', 'direction',
        ]))->map(fn ($value) => is_string($value) ? trim($value) : $value)->all());
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'cinema_id' => ['nullable', 'integer', 'min:1'],
            'sales_channel' => ['nullable', Rule::in(['online', 'counter'])],
            'ticket_status' => ['nullable', Rule::in([...BookingTicketDelivery::STATUSES, 'none'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['paid_at', 'booking_code', 'total_amount', 'show_date'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50])],
        ];
    }
}
