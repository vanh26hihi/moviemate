<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SaveRoomLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('layout'))) {
            try {
                $this->merge(['layout' => json_decode($this->input('layout'), true, flags: JSON_THROW_ON_ERROR)]);
            } catch (\JsonException) {
                throw ValidationException::withMessages(['layout' => 'Dữ liệu sơ đồ ghế không hợp lệ.']);
            }
        }
    }

    public function rules(): array
    {
        return [
            'layout' => ['required', 'array'],
            'layout.schema_version' => ['nullable', 'integer', 'in:2,3'],
            'layout.expected_updated_at' => ['nullable', 'string', 'max:40'],
            'layout.name' => ['nullable', 'string', 'max:255'],
            'layout.rows' => ['required', 'integer', 'min:1', 'max:30'],
            'layout.columns' => ['required', 'integer', 'min:1', 'max:40'],
            'layout.screen_position' => ['required', 'in:top,bottom'],
            'layout.cells' => ['present', 'array', 'max:1200'],
            'layout.cells.*' => ['array'],
        ];
    }
}
