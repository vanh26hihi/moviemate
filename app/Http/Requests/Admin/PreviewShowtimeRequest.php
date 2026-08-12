<?php

namespace App\Http\Requests\Admin;

use App\Models\Movie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewShowtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movie_id' => ['required', 'integer', Rule::exists('movies', 'id')->whereIn('status', Movie::SCHEDULABLE_STATUSES)],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'show_date' => ['required', 'date_format:Y-m-d'],
            'show_time' => ['required', 'date_format:H:i'],
            'showtime_id' => ['nullable', 'integer', 'exists:showtimes,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'movie_id.required' => 'Vui lòng chọn phim.',
            'movie_id.exists' => 'Phim đã chọn không tồn tại hoặc không thể xếp lịch.',
            'room_id.required' => 'Vui lòng chọn phòng chiếu.',
            'room_id.exists' => 'Phòng chiếu đã chọn không tồn tại.',
            'show_date.required' => 'Vui lòng chọn ngày chiếu.',
            'show_date.date_format' => 'Ngày chiếu không đúng định dạng.',
            'show_time.required' => 'Vui lòng chọn giờ bắt đầu.',
            'show_time.date_format' => 'Giờ bắt đầu không đúng định dạng.',
            'showtime_id.exists' => 'Suất chiếu cần xem trước không tồn tại.',
        ];
    }
}
