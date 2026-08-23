<?php

namespace App\Http\Requests\Admin;

class UpdateShowtimeRequest extends StoreShowtimeRequest {
    public function rules(): array
{
    return [
        'movie_id' => [
            'required',
            'integer',
            'exists:movies,id',
        ],

        'room_id' => [
            'required',
            'integer',
            'exists:rooms,id',
        ],

        'show_date' => [
            'required',
            'date',
        ],

        'show_time' => [
            'required',
            'date_format:H:i',
        ],

        'price' => [
            'required',
            'integer',
            'min:0',
        ],

        'vip_price' => [
            'nullable',
            'integer',
            'min:0',
        ],

        'status' => [
            'required',
            'string',
            'in:active,cancelled,finished',
        ],
    ];
}

public function messages(): array
{
    return [
        'movie_id.required' => 'Vui lòng chọn phim.',
        'movie_id.exists' => 'Phim đã chọn không tồn tại.',

        'room_id.required' => 'Vui lòng chọn phòng chiếu.',
        'room_id.exists' => 'Phòng chiếu đã chọn không tồn tại.',

        'show_date.required' => 'Vui lòng nhập ngày chiếu.',
        'show_date.date' => 'Ngày chiếu không hợp lệ.',

        'show_time.required' => 'Vui lòng nhập giờ chiếu.',
        'show_time.date_format' => 'Giờ chiếu phải có định dạng HH:mm.',

        'price.required' => 'Vui lòng nhập giá vé thường.',
        'price.integer' => 'Giá vé thường phải là số nguyên.',
        'price.min' => 'Giá vé thường không được nhỏ hơn 0.',

        'vip_price.integer' => 'Giá vé VIP phải là số nguyên.',
        'vip_price.min' => 'Giá vé VIP không được nhỏ hơn 0.',

        'status.required' => 'Vui lòng chọn trạng thái suất chiếu.',
        'status.in' => 'Trạng thái suất chiếu không hợp lệ.',
    ];
}
}
