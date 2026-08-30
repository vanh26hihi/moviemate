<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowtimeBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $timezone = (string) config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $from = trim((string) $this->input('from', ''));
        $to = trim((string) $this->input('to', ''));

        if ($from === '') {
            $from = $today->startOfWeek()->toDateString();
        }
        if ($to === '') {
            $to = CarbonImmutable::parse($from, $timezone)->addDays(6)->toDateString();
        }

        $this->merge([
            'from' => $from,
            'to' => $to,
            'search' => trim((string) $this->input('search', '')),
            'room_id' => $this->integerOrNull('room_id'),
            'movie_id' => $this->integerOrNull('movie_id'),
            'presentation_format_id' => $this->integerOrNull('presentation_format_id'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:120'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'movie_id' => ['nullable', 'integer', 'exists:movies,id'],
            'presentation_format_id' => ['nullable', 'integer', 'exists:presentation_formats,id'],
            'status' => ['nullable', Rule::in(['active', 'cancelled'])],
            'layout' => ['nullable', Rule::in(['timeline', 'list'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = CarbonImmutable::parse((string) $this->input('from'));
            $to = CarbonImmutable::parse((string) $this->input('to'));
            if ($from->diffInDays($to) > 30) {
                $validator->errors()->add('to', 'Khoảng thời gian xem lịch không được vượt quá 31 ngày.');
            }
        });
    }

    /** @return array{from: CarbonImmutable, to: CarbonImmutable} */
    public function period(string $timezone): array
    {
        return [
            'from' => CarbonImmutable::parse($this->validated('from'), $timezone)->startOfDay(),
            'to' => CarbonImmutable::parse($this->validated('to'), $timezone)->endOfDay(),
        ];
    }

    private function integerOrNull(string $key): ?int
    {
        $value = $this->input($key);

        return is_scalar($value) && preg_match('/^\d+$/', (string) $value) ? (int) $value : null;
    }
}
