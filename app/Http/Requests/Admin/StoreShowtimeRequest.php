<?php

namespace App\Http\Requests\Admin;

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
            'movie_id' => ['required', 'integer', 'exists:movies,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'show_date' => ['required', 'date_format:Y-m-d'],
            'show_time' => ['required', 'date_format:H:i'],
            'price' => ['required', 'numeric', 'min:0'],
            'vip_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'cancelled', 'finished'])],
        ];
    }

    public function messages(): array
    {
        return [
            'movie_id.required' => 'Vui lòng chọn phim.',
            'movie_id.integer' => 'Phim đã chọn không hợp lệ.',
            'movie_id.exists' => 'Phim đã chọn không tồn tại.',
            'room_id.required' => 'Vui lòng chọn phòng chiếu.',
            'room_id.integer' => 'Phòng chiếu đã chọn không hợp lệ.',
            'room_id.exists' => 'Phòng chiếu đã chọn không tồn tại.',
            'show_date.required' => 'Vui lòng chọn ngày chiếu.',
            'show_date.date_format' => 'Ngày chiếu phải đúng định dạng năm-tháng-ngày.',
            'show_time.required' => 'Vui lòng chọn giờ bắt đầu.',
            'show_time.date_format' => 'Giờ bắt đầu phải đúng định dạng giờ:phút.',
            'price.required' => 'Vui lòng nhập giá vé thường.',
            'price.numeric' => 'Giá vé thường phải là số.',
            'price.min' => 'Giá vé thường không được âm.',
            'vip_price.numeric' => 'Giá vé VIP phải là số.',
            'vip_price.min' => 'Giá vé VIP không được âm.',
            'status.required' => 'Vui lòng chọn trạng thái suất chiếu.',
            'status.in' => 'Trạng thái suất chiếu không hợp lệ.',
        ];
    }
}
