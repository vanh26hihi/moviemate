<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\PriceBookAdminAccess;
use Illuminate\Foundation\Http\FormRequest;

final class PreviewPriceBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PriceBookAdminAccess::class)->canView($this->user());
    }

    public function rules(): array
    {
        return [
            'cinema_id' => ['required', 'integer', 'exists:cinemas,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'seat_type_id' => ['required', 'integer', 'exists:seat_types,id'],
            'showtime_local_start' => ['required', 'date'],
        ];
    }
}
