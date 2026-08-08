<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoomLayoutTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('layout_templates.manage');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('layout'))) {
            $decoded = json_decode($this->input('layout'), true);
            if (is_array($decoded)) {
                $this->merge(['layout' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        $id = $this->route('layout_template')?->id;

        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('room_layout_templates', 'code')->ignore($id)],
            'name' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'room_type' => ['nullable', Rule::in(['2D', '3D', 'IMAX', '4DX'])],
            'layout' => ['required', 'array'],
        ];
    }
}
