<?php

namespace App\Http\Requests\Admin;

use App\Domain\Money\VndAmount;
use App\Models\FoodItem;
use App\Rules\WholeVndAmount;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use OverflowException;

class SaveFoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', new WholeVndAmount('Giá món ăn', FoodItem::MAX_PRICE)],
            'image' => ['nullable', 'image', 'max:4096'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên món ăn.',
            'name.string' => 'Tên món ăn không hợp lệ.',
            'name.max' => 'Tên món ăn không được vượt quá 255 ký tự.',
            'description.string' => 'Mô tả món ăn không hợp lệ.',
            'price.required' => 'Vui lòng nhập giá món ăn.',
            'image.image' => 'Ảnh món ăn phải là tệp hình ảnh.',
            'image.max' => 'Ảnh món ăn không được vượt quá 4 MB.',
            'active.boolean' => 'Trạng thái món ăn không hợp lệ.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('price') || $this->input('price') === null) {
            return;
        }

        try {
            $this->merge([
                'price' => VndAmount::fromInput($this->input('price'), FoodItem::MAX_PRICE)->value(),
            ]);
        } catch (InvalidArgumentException|OverflowException) {
            // Keep invalid input unchanged so WholeVndAmount returns the field-specific error.
        }
    }
}
