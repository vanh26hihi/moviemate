<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class BulkShowtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['required', 'array'],
            'rows.*.row_key' => ['required', 'string', 'max:100', 'distinct:strict'],
            'rows.*.movie_id' => ['required', 'integer'],
            'rows.*.presentation_format_id' => ['required', 'integer', 'exists:presentation_formats,id'],
            'rows.*.room_id' => ['required', 'integer'],
            'rows.*.show_date' => ['required', 'date_format:Y-m-d'],
            'rows.*.show_time' => ['required', 'date_format:H:i'],
            'rows.*.cinema_id' => ['prohibited'],
            'rows.*.room_layout_id' => ['prohibited'],
            'rows.*.status' => ['prohibited'],
            'rows.*.price' => ['prohibited'],
            'rows.*.vip_price' => ['prohibited'],
            'rows.*.pricing_version' => ['prohibited'],
            'rows.*.end_time' => ['prohibited'],
            'rows.*.cleaning_time' => ['prohibited'],
            'rows.*.room_ready' => ['prohibited'],
            'rows.*.timezone' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.*.presentation_format_id.required' => 'Vui lòng chọn định dạng trình chiếu.',
            'rows.*.presentation_format_id.integer' => 'Định dạng trình chiếu đã chọn không hợp lệ.',
            'rows.*.presentation_format_id.exists' => 'Định dạng trình chiếu đã chọn không tồn tại.',
        ];
    }

    /** @return list<array{row_key: string, movie_id: int, presentation_format_id: int, room_id: int, show_date: string, show_time: string}> */
    public function rows(): array
    {
        return array_values($this->validated('rows'));
    }
}
