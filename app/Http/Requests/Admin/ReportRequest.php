<?php

namespace App\Http\Requests\Admin;

use App\Models\Booking;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only([
            'from', 'to', 'cinema', 'sales_channel', 'provider', 'metric',
        ]))->map(fn ($value) => is_string($value) ? trim($value) : $value)->all());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'cinema' => ['nullable', 'string', 'max:64', 'regex:/^(all|[A-Za-z0-9_-]+)$/'],
            'sales_channel' => ['nullable', Rule::in(Booking::SALES_CHANNELS)],
            'provider' => ['nullable', Rule::in([...Payment::SUPPORTED_PROVIDERS, Payment::PROVIDER_COUNTER_CASH])],
            'metric' => ['nullable', Rule::in(['revenue', 'logical_tickets', 'physical_seats'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('from') || ! $this->filled('to')) {
                return;
            }

            $from = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('from'));
            $to = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('to'));
            if ($from && $to && $from->diffInDays($to) > 365) {
                $validator->errors()->add('to', 'Khoảng thời gian báo cáo không được vượt quá 366 ngày.');
            }
        });
    }
}
