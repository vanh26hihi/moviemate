<?php

namespace App\Http\Requests\Admin;

use App\Models\RoomLayoutTemplate;
use App\Models\RoomType;
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
        if ($this->filled('room_type')) {
            $this->merge(['room_type' => RoomType::normalizeCode((string) $this->input('room_type'))]);
        }
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
            'room_type' => [
                'nullable',
                Rule::exists('room_types', 'code')->where(function ($query): void {
                    $current = $this->route('layout_template');
                    $query->where('is_active', true)
                        ->when($current instanceof RoomLayoutTemplate && $current->room_type, fn ($query) => $query->orWhere('code', $current->room_type));
                }),
            ],
            'layout' => ['required', 'array'],
        ];
    }
}
