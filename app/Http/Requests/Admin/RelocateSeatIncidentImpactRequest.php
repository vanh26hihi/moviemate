<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class RelocateSeatIncidentImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['replacement_seat_id' => ['required', 'integer', 'min:1']];
    }
}
