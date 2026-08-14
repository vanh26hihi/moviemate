<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\PriceBookAdminAccess;
use Illuminate\Foundation\Http\FormRequest;

final class CopyPriceBookVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PriceBookAdminAccess::class)->canManage($this->user());
    }

    public function rules(): array
    {
        return [
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_until' => ['nullable', 'date_format:Y-m-d', 'after:effective_from'],
        ];
    }
}
