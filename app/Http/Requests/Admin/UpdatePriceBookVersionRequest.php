<?php

namespace App\Http\Requests\Admin;

use App\Models\Showtime;
use App\Services\Admin\PriceBookAdminAccess;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePriceBookVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PriceBookAdminAccess::class)->canManage($this->user());
    }

    public function rules(): array
    {
        return [
            'base_price_vnd' => ['required', 'integer', 'min:1', 'max:'.Showtime::MAX_PRICE],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_until' => ['nullable', 'date_format:Y-m-d', 'after:effective_from'],
        ];
    }
}
