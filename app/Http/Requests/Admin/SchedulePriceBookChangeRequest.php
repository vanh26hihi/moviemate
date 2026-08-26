<?php

namespace App\Http\Requests\Admin;

use App\Models\PriceBookVersion;
use App\Models\SeatType;
use App\Models\Showtime;
use App\Services\Admin\PriceBookAdminAccess;
use App\Services\PriceBookScheduleChangeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SchedulePriceBookChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PriceBookAdminAccess::class)->canManage($this->user());
    }

    public function rules(): array
    {
        $seatTypeIds = SeatType::query()->where('status', true)
            ->orderBy('id')->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $rules = [
            'change_kind' => ['required', Rule::in(PriceBookScheduleChangeService::KINDS)],
            'change_date' => ['required', 'date_format:Y-m-d'],
            'ticket_prices' => ['required', 'array', 'size:'.count($seatTypeIds)],
        ];

        foreach ($seatTypeIds as $seatTypeId) {
            $rules["ticket_prices.{$seatTypeId}"] = [
                'required', 'integer', 'min:1', 'max:'.Showtime::MAX_PRICE,
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('change_date')) {
                return;
            }
            $version = $this->route('version');
            if (! $version instanceof PriceBookVersion
                || $version->status !== PriceBookVersion::STATUS_PUBLISHED) {
                $validator->errors()->add('change_date', 'Chỉ có thể thay lịch của bảng giá đang phát hành.');

                return;
            }

            $date = (string) $this->input('change_date');
            $from = $version->effective_from?->toDateString();
            $until = $version->effective_until?->toDateString();
            if ($from === null || $date < $from || ($until !== null && $date >= $until)) {
                $validator->errors()->add(
                    'change_date',
                    'Ngày thay đổi phải nằm trong thời gian áp dụng của bảng giá này.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'change_kind.required' => 'Vui lòng chọn cách thay đổi giá.',
            'change_kind.in' => 'Cách thay đổi giá không hợp lệ.',
            'change_date.required' => 'Vui lòng chọn ngày thay đổi giá.',
            'change_date.date_format' => 'Ngày thay đổi giá không đúng định dạng.',
            'ticket_prices.required' => 'Vui lòng nhập đầy đủ giá cho các loại vé.',
            'ticket_prices.size' => 'Danh sách giá không khớp với các loại vé đang hoạt động.',
            'ticket_prices.*.required' => 'Vui lòng nhập giá cho loại vé này.',
            'ticket_prices.*.integer' => 'Giá vé phải là số nguyên theo VND.',
            'ticket_prices.*.min' => 'Giá vé phải lớn hơn 0 ₫.',
            'ticket_prices.*.max' => 'Giá vé vượt giới hạn được hệ thống hỗ trợ.',
        ];
    }
}
