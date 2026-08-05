<?php

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only([
            'booking_code', 'reference', 'sort', 'direction',
        ]))->map(fn ($value) => is_string($value) ? trim($value) : $value)->all());
    }

    public function rules(): array
    {
        return [
            'booking_code' => ['nullable', 'string', 'max:60'],
            'provider' => ['nullable', Rule::in(Payment::SUPPORTED_PROVIDERS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Payment::STATUSES)],
            'verified' => ['nullable', Rule::in(['yes', 'no'])],
            'review' => ['nullable', Rule::in(['yes', 'no'])],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'paid_from' => ['nullable', 'date_format:Y-m-d'],
            'paid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:paid_from'],
            'amount_min' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'amount_max' => ['nullable', 'integer', 'min:0', 'max:1000000000', 'gte:amount_min'],
            'amount_mismatch' => ['nullable', Rule::in(['yes', 'no'])],
            'reconciled' => ['nullable', Rule::in(['yes', 'no'])],
            'sort' => ['nullable', Rule::in(['created_at', 'amount', 'verified_at', 'last_queried_at', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50])],
        ];
    }
}
