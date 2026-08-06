<?php

namespace App\Http\Requests\Admin;

use App\Models\TicketCheckinEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexTicketCheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_code' => ['nullable', 'string', 'max:60'],
            'movie' => ['nullable', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:100'],
            'showtime_id' => ['nullable', 'integer', 'min:1'],
            'actor' => ['nullable', 'string', 'max:100'],
            'result' => ['nullable', Rule::in(TicketCheckinEvent::RESULTS)],
            'reason' => ['nullable', 'string', 'max:64', 'alpha_dash:ascii'],
            'scanned_from' => ['nullable', 'date_format:Y-m-d'],
            'scanned_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:scanned_from'],
            'duplicates_only' => ['nullable', Rule::in(['yes'])],
            'rejected_only' => ['nullable', Rule::in(['yes'])],
            'sort' => ['nullable', Rule::in(['scanned_at', 'result', 'id'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50])],
        ];
    }
}
