<?php

namespace App\Http\Requests\Admin;

use App\Models\Movie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SaveMovieRequest extends FormRequest
{
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
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('movies', 'slug')->ignore($movie instanceof Movie ? $movie->id : null)],
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
        ];
    }
}
