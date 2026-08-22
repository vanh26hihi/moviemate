<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CopyShowtimeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['room', 'cinema'])],
            'cinema_id' => ['required', 'integer'],
            'room_id' => ['nullable', 'required_if:scope,room', 'prohibited_unless:scope,room', 'integer'],
            'source_date' => ['required', 'date_format:Y-m-d'],
            'target_date' => ['required', 'date_format:Y-m-d', 'different:source_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'scope.in' => 'Phạm vi sao chép không hợp lệ.',
            'cinema_id.required' => 'Vui lòng chọn chi nhánh.',
            'room_id.required_if' => 'Vui lòng chọn phòng cho phạm vi một phòng.',
            'room_id.prohibited_unless' => 'Không gửi phòng khi sao chép toàn chi nhánh.',
            'source_date.required' => 'Vui lòng chọn ngày nguồn.',
            'target_date.required' => 'Vui lòng chọn ngày đích.',
            'target_date.different' => 'Ngày đích phải khác ngày nguồn.',
        ];
    }
}
