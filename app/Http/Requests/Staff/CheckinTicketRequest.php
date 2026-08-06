<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

final class CheckinTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['ticket' => ['required', 'string', 'max:512']];
    }

    public function messages(): array
    {
        return [
            'ticket.required' => 'Vui lòng quét hoặc nhập mã vé.',
            'ticket.max' => 'Mã vé không hợp lệ.',
        ];
    }
}
