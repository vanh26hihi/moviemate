<?php

namespace App\Http\Requests\Admin;

use App\Models\Movie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShowtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movie_id' => ['required', 'integer', Rule::exists('movies', 'id')->whereIn('status', Movie::SCHEDULABLE_STATUSES)],
            'presentation_format_id' => ['required', 'integer', 'exists:presentation_formats,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'show_date' => ['required', 'date_format:Y-m-d'],
            'show_time' => ['required', 'date_format:H:i'],
            'status' => ['sometimes', Rule::in(['active'])],
        ];
    }

    public function messages(): array
    {
        return [
            'movie_id.required' => 'Vui lòng chọn phim.',
            'movie_id.integer' => 'Phim đã chọn không hợp lệ.',
            'movie_id.exists' => 'Phim đã chọn không tồn tại.',
            'presentation_format_id.required' => 'Vui lòng chọn định dạng trình chiếu.',
            'presentation_format_id.integer' => 'Định dạng trình chiếu đã chọn không hợp lệ.',
            'presentation_format_id.exists' => 'Định dạng trình chiếu đã chọn không tồn tại.',
            'room_id.required' => 'Vui lòng chọn phòng chiếu.',
            'room_id.integer' => 'Phòng chiếu đã chọn không hợp lệ.',
            'room_id.exists' => 'Phòng chiếu đã chọn không tồn tại.',
            'show_date.required' => 'Vui lòng chọn ngày chiếu.',
            'show_date.date_format' => 'Ngày chiếu phải đúng định dạng năm-tháng-ngày.',
            'show_time.required' => 'Vui lòng chọn giờ bắt đầu.',
            'show_time.date_format' => 'Giờ bắt đầu phải đúng định dạng giờ:phút.',
            'status.in' => 'Trạng thái suất chiếu không hợp lệ.',
        ];
    }
}
