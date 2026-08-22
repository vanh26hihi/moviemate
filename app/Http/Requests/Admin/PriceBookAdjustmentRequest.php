<?php

namespace App\Http\Requests\Admin;

use App\Models\PriceBookAdjustment;
use App\Models\Showtime;
use App\Services\Admin\PriceBookAdminAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PriceBookAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PriceBookAdminAccess::class)->canManage($this->user());
    }

    public function rules(): array
    {
        return [
            'dimension' => ['required', Rule::in(PriceBookAdjustment::DIMENSIONS)],
            'label' => ['required', 'string', 'max:255'],
            'amount_vnd' => ['required', 'integer', 'not_in:0', 'between:-'.Showtime::MAX_PRICE.','.Showtime::MAX_PRICE],
            'seat_type_id' => ['required_if:dimension,seat_type', 'prohibited_unless:dimension,seat_type', 'integer', 'exists:seat_types,id'],
            'room_type_id' => ['required_if:dimension,room_type', 'prohibited_unless:dimension,room_type', 'integer', 'exists:room_types,id'],
            'cinema_id' => ['required_if:dimension,cinema', 'prohibited_unless:dimension,cinema', 'integer', 'exists:cinemas,id'],
            'room_id' => ['required_if:dimension,room', 'prohibited_unless:dimension,room', 'integer', 'exists:rooms,id'],
            'time_start' => ['required_if:dimension,time_window', 'prohibited_unless:dimension,time_window', 'date_format:H:i'],
            'time_end' => ['required_if:dimension,time_window', 'prohibited_unless:dimension,time_window', 'date_format:H:i', 'different:time_start'],
            'holiday_date_from' => ['required_if:dimension,holiday', 'prohibited_unless:dimension,holiday', 'date_format:Y-m-d'],
            'holiday_date_until' => ['required_if:dimension,holiday', 'prohibited_unless:dimension,holiday', 'date_format:Y-m-d', 'after:holiday_date_from'],
            'weekend_days' => ['required_if:dimension,weekend', 'prohibited_unless:dimension,weekend', 'array', 'min:1'],
            'weekend_days.*' => ['integer', 'distinct', 'between:1,7'],
        ];
    }
}
