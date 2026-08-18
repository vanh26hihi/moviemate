<?php

namespace App\Http\Requests\Admin;

use App\Models\RoomType;
use App\Support\AdminUniqueRules;
use Illuminate\Foundation\Http\FormRequest;

final class SaveRoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true
            && $this->user()->hasPermission('admin.access')
            && $this->user()->hasPermission('room_types.manage');
    }

    protected function prepareForValidation(): void
    {
        $name = is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name');
        $codeSource = $this->filled('code') ? (string) $this->input('code') : (string) $name;
        $this->merge([
            'name' => $name,
            'code' => RoomType::normalizeCode($codeSource),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    public function rules(): array
    {
        $roomType = $this->route('roomType');

        return [
            'name' => [
                'required', 'string', 'max:120',
                AdminUniqueRules::roomTypeName($roomType instanceof RoomType ? $roomType : null),
            ],
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9]+(?:_[A-Z0-9]+)*$/',
                AdminUniqueRules::roomTypeCode($roomType instanceof RoomType ? $roomType : null),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:-10000,10000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên loại phòng',
            'code' => 'mã loại phòng',
            'description' => 'mô tả',
            'is_active' => 'trạng thái',
            'sort_order' => 'thứ tự',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Tên loại phòng này đã tồn tại.',
            'code.unique' => 'Mã loại phòng này đã tồn tại.',
        ];
    }
}
