<?php

namespace App\Http\Requests\Admin;

use App\Models\SeatType;
use App\Models\Showtime;
use App\Services\Admin\PriceBookAdminAccess;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateSimpleTicketPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PriceBookAdminAccess::class)->canManage($this->user());
    }

    public function rules(): array
    {
        $seatTypeIds = SeatType::query()
            ->where('status', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $rules = [
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'ticket_prices' => ['required', 'array', 'size:'.count($seatTypeIds)],
        ];

        foreach ($seatTypeIds as $seatTypeId) {
            $rules["ticket_prices.{$seatTypeId}"] = [
                'required',
                'integer',
                'min:1',
                'max:'.Showtime::MAX_PRICE,
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'ticket_prices.required' => 'Vui lòng nhập đầy đủ giá bán cho các loại vé đang hoạt động.',
            'ticket_prices.size' => 'Danh sách giá vé không khớp với các loại vé đang hoạt động.',
            'ticket_prices.*.required' => 'Vui lòng nhập giá bán cho loại vé này.',
            'ticket_prices.*.integer' => 'Giá vé phải là số nguyên theo VND.',
            'ticket_prices.*.min' => 'Giá vé phải lớn hơn 0 ₫.',
            'ticket_prices.*.max' => 'Giá vé vượt giới hạn được hệ thống hỗ trợ.',
            'effective_from.required' => 'Vui lòng chọn ngày bắt đầu áp dụng.',
            'effective_end_date.after_or_equal' => 'Ngày cuối cùng áp dụng không được trước ngày bắt đầu.',
        ];
    }
}
