<?php

namespace App\Http\Requests\Admin;

use App\Models\ShowtimeCancellation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CancelShowtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('showtimes.delete') === true;
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', Rule::in(array_keys(ShowtimeCancellation::REASONS))],
            'reason_note' => ['nullable', 'string', 'max:500', 'required_if:reason_code,other'],
            'confirm_cancellation' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason_code.required' => 'Vui lòng chọn lý do hủy suất chiếu.',
            'reason_note.required_if' => 'Vui lòng mô tả lý do khi chọn “Lý do khác”.',
            'confirm_cancellation.accepted' => 'Bạn cần xác nhận đã hiểu tác động trước khi hủy suất chiếu.',
        ];
    }
}
