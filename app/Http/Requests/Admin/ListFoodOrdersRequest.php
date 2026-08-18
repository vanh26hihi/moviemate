<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListFoodOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => is_string($this->input('search')) ? trim($this->input('search')) : $this->input('search'),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'voucher_status' => ['nullable', Rule::in(['unprinted', 'printed', 'missing'])],
            'sort' => ['nullable', Rule::in(['paid_at', 'total_amount', 'booking_code'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50])],
        ];
    }
}
