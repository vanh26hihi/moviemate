<?php

namespace App\Http\Requests\Admin;

use App\Models\PresentationFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavePresentationFormatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true
            && $this->user()->hasPermission('admin.access')
            && $this->user()->hasPermission('presentation_formats.manage');
    }

    protected function prepareForValidation(): void
    {
        $name = is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name');
        $codeSource = $this->filled('code') ? (string) $this->input('code') : (string) $name;

        $this->merge([
            'code' => PresentationFormat::normalizeCode($codeSource),
            'name' => $name,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $format = $this->route('presentationFormat');

        return [
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9]+(?:_[A-Z0-9]+)*$/',
                Rule::unique('presentation_formats', 'code')->ignore($format instanceof PresentationFormat ? $format->id : null),
            ],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('presentation_formats', 'name')->ignore($format instanceof PresentationFormat ? $format->id : null),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'between:0,4294967295'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'mã định dạng',
            'name' => 'tên định dạng',
            'description' => 'mô tả',
            'sort_order' => 'thứ tự',
        ];
    }
}
