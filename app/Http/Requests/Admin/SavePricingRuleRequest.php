<?php

namespace App\Http\Requests\Admin;

use App\Models\CinemaPricingRule;
use App\Models\RoomType;
use App\Models\Seat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cinema_id' => $this->filled('cinema_id') ? $this->input('cinema_id') : null,
            'room_id' => $this->filled('room_id') ? $this->input('room_id') : null,
            'seat_type' => $this->filled('seat_type') ? strtolower(trim((string) $this->input('seat_type'))) : null,
            'room_type' => $this->filled('room_type') ? RoomType::normalizeCode((string) $this->input('room_type')) : null,
            'stacks_with_weekend' => $this->boolean('stacks_with_weekend'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'rule_type' => ['required', Rule::in(CinemaPricingRule::TYPES)],
            'cinema_id' => ['nullable', 'integer', 'exists:cinemas,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'seat_type' => ['nullable', Rule::in(Seat::TYPES), Rule::requiredIf($this->input('rule_type') === 'seat_type')],
            'room_type' => [
                'nullable',
                Rule::requiredIf($this->input('rule_type') === 'room_type'),
                Rule::exists('room_types', 'code')->where(function ($query): void {
                    $current = $this->route('pricing_rule');
                    $query->where('is_active', true)
                        ->when($current instanceof CinemaPricingRule && $current->room_type, fn ($query) => $query->orWhere('code', $current->room_type));
                }),
            ],
            'days_of_week' => ['nullable', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7', 'distinct'],
            'date_start' => ['nullable', 'date_format:Y-m-d', Rule::requiredIf($this->input('rule_type') === 'holiday')],
            'date_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_start'],
            'time_start' => ['nullable', 'date_format:H:i', Rule::requiredIf($this->input('rule_type') === 'time_window')],
            'time_end' => ['nullable', 'date_format:H:i', 'different:time_start', Rule::requiredIf($this->input('rule_type') === 'time_window')],
            'amount_vnd' => ['required', 'integer', 'between:-99999999,99999999'],
            'priority' => ['required', 'integer', 'between:-100000,100000'],
            'stacks_with_weekend' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
