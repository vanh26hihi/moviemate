<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\PriceBookAdminAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'effective_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('effective_until') && $this->filled('effective_end_date')) {
                $validator->errors()->add(
                    'effective_end_date',
                    'Chỉ gửi một cách xác định ngày kết thúc của bảng giá.',
                );
            }
        });
    }
}
