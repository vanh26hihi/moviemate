<?php

namespace App\Http\Requests\Admin;

use App\Models\Movie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListMoviesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim((string) $this->input('search', '')),
            'country' => trim((string) $this->input('country', '')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in([
                Movie::STATUS_DRAFT,
                Movie::STATUS_COMING_SOON,
                Movie::STATUS_NOW_SHOWING,
                Movie::STATUS_INACTIVE,
                Movie::STATUS_ARCHIVED,
            ])],
            'genre' => ['nullable', 'integer', 'exists:genres,id'],
            'country' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['updated_at', 'title', 'release_date'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
