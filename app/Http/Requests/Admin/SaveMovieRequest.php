<?php

namespace App\Http\Requests\Admin;

use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Support\AdminUniqueRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class SaveMovieRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $title = preg_replace('/\s+/u', ' ', trim((string) $this->input('title', ''))) ?: '';

        $this->merge([
            'title' => $title,
            'slug' => filled($this->input('slug')) ? trim((string) $this->input('slug')) : null,
            'country' => filled($this->input('country')) ? trim((string) $this->input('country')) : null,
        ]);
    }

    public function authorize(): bool
    {
        $permission = $this->route('movie') instanceof Movie
            ? 'movies.update'
            : 'movies.create';

        return $this->user()?->isActive() === true
            && $this->user()->hasPermission('admin.access')
            && $this->user()->hasPermission($permission);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $movie = $this->route('movie');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', AdminUniqueRules::movieSlug($movie instanceof Movie ? $movie : null)],
            'description' => ['nullable', 'string'],
            'poster' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(4 * 1024)],
            'cover_image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(8 * 1024)],
            'trailer_url' => ['nullable', 'url'],
            'country' => ['nullable', 'string', 'max:100'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'age_rating' => ['nullable', 'string', 'max:10'],
            'release_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in([
                Movie::STATUS_DRAFT, Movie::STATUS_COMING_SOON, Movie::STATUS_NOW_SHOWING,
                Movie::STATUS_INACTIVE, Movie::STATUS_ARCHIVED,
            ])],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['integer', 'exists:genres,id'],
            'presentation_format_ids' => ['nullable', 'array'],
            'presentation_format_ids.*' => ['integer', 'distinct', 'exists:presentation_formats,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('presentation_format_ids')
                || $validator->errors()->has('presentation_format_ids.*')) {
                return;
            }

            $ids = collect($this->input('presentation_format_ids', []))
                ->map(fn ($id): int => (int) $id)->unique()->values();
            $movie = $this->route('movie');
            $currentIds = $movie instanceof Movie
                ? $movie->supportedPresentationFormats()->pluck('presentation_formats.id')->map(fn ($id): int => (int) $id)
                : collect();
            $newIds = $ids->diff($currentIds);

            if ($newIds->isNotEmpty() && PresentationFormat::query()->whereIn('id', $newIds)->where('is_active', false)->exists()) {
                $validator->errors()->add('presentation_format_ids', 'Không thể thêm định dạng trình chiếu đã lưu trữ.');
            }

            if ($movie instanceof Movie && in_array($movie->status, Movie::SCHEDULABLE_STATUSES, true)
                && ! PresentationFormat::query()->active()->whereIn('id', $ids)->exists()) {
                $validator->errors()->add('presentation_format_ids', 'Phim đang có thể xếp lịch phải hỗ trợ ít nhất một định dạng đang sử dụng.');
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'tên phim',
            'slug' => 'đường dẫn phim',
            'description' => 'mô tả phim',
            'duration' => 'thời lượng phim',
            'release_date' => 'ngày khởi chiếu',
            'status' => 'trạng thái phim',
            'presentation_format_ids' => 'định dạng hỗ trợ',
            'presentation_format_ids.*' => 'định dạng hỗ trợ',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Đường dẫn phim này đã tồn tại.',
        ];
    }
}
