<?php

namespace App\Http\Requests\Admin;

use App\Models\RoomLayoutTemplate;
use App\Models\RoomType;
use App\Support\AdminUniqueRules;
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
        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', AdminUniqueRules::layoutTemplateCode($this->route('layout_template'))],
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

    public function attributes(): array
    {
        return [
            'code' => 'mã mẫu sơ đồ',
            'name' => 'tên mẫu sơ đồ',
            'description' => 'mô tả mẫu sơ đồ',
            'room_type' => 'loại phòng',
            'layout' => 'lưới bố trí logic',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Mã mẫu sơ đồ này đã tồn tại.',
        ];
    }
}
