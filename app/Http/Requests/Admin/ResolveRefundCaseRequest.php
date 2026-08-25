<?php

namespace App\Http\Requests\Admin;

use App\Models\RefundCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResolveRefundCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('refunds.resolve') === true;
    }

    public function rules(): array
    {
        return [
            'resolved_amount' => ['required', 'integer', 'min:0'],
            'resolution_method' => ['required', Rule::in(array_keys(RefundCase::RESOLUTION_METHODS))],
            'resolution_reference' => ['required', 'string', 'max:200'],
            'resolution_note' => ['nullable', 'string', 'max:500'],
            'confirm_resolution' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'resolved_amount.required' => 'Vui lòng nhập đúng số tiền đã hoàn.',
            'resolution_method.required' => 'Vui lòng chọn phương thức hoàn tiền thực tế.',
            'resolution_reference.required' => 'Vui lòng nhập mã tham chiếu hoặc bằng chứng đối soát.',
            'confirm_resolution.accepted' => 'Bạn cần xác nhận đây là giao dịch hoàn tiền đã thực hiện bên ngoài hệ thống.',
        ];
    }
}
