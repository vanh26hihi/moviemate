<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\PresentationFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MoviePresentationFormatService
{
    public function __construct(private readonly FutureActiveShowtimeDependency $dependencies) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $genreIds
     * @param  list<int>  $formatIds
     */
    public function create(array $attributes, array $genreIds, array $formatIds): Movie
    {
        return DB::transaction(function () use ($attributes, $genreIds, $formatIds): Movie {
            $formats = $this->lockedFormats($formatIds);
            $this->assertKnownFormats($formatIds, $formats);
            if ($formats->contains(fn (PresentationFormat $format): bool => ! $format->is_active)) {
                throw ValidationException::withMessages([
                    'presentation_format_ids' => 'Chỉ có thể chọn định dạng trình chiếu đang sử dụng.',
                ]);
            }

            $movie = Movie::query()->create($attributes);
            $movie->genres()->sync($genreIds);
            $movie->supportedPresentationFormats()->sync($formatIds);

            return $movie;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $genreIds
     * @param  list<int>  $formatIds
     */
    public function update(Movie $movie, array $attributes, array $genreIds, array $formatIds): Movie
    {
        return DB::transaction(function () use ($movie, $attributes, $genreIds, $formatIds): Movie {
            $locked = Movie::query()->whereKey($movie->id)->lockForUpdate()->firstOrFail();
            $currentIds = $locked->supportedPresentationFormats()->pluck('presentation_formats.id')->map(fn ($id): int => (int) $id)->all();
            $allIds = collect([...$currentIds, ...$formatIds])->unique()->sort()->values()->all();
            $formats = $this->lockedFormats($allIds);
            $this->assertKnownFormats($formatIds, $formats);

            $additions = array_values(array_diff($formatIds, $currentIds));
            if ($formats->whereIn('id', $additions)->contains(fn (PresentationFormat $format): bool => ! $format->is_active)) {
                throw ValidationException::withMessages([
                    'presentation_format_ids' => 'Không thể thêm định dạng trình chiếu đã lưu trữ.',
                ]);
            }

            if (in_array($locked->status, Movie::SCHEDULABLE_STATUSES, true)
                && ! $formats->whereIn('id', $formatIds)->contains(fn (PresentationFormat $format): bool => $format->is_active)) {
                throw ValidationException::withMessages([
                    'presentation_format_ids' => 'Phim đang có thể xếp lịch phải hỗ trợ ít nhất một định dạng đang sử dụng.',
                ]);
            }

            $removals = array_values(array_diff($currentIds, $formatIds));
            $conflictingFormatId = $this->dependencies->conflictingMovieFormatId((int) $locked->id, $removals, lock: true);
            if ($conflictingFormatId !== null) {
                $name = $formats->firstWhere('id', $conflictingFormatId)?->name ?? (string) $conflictingFormatId;
                throw ValidationException::withMessages([
                    'presentation_format_ids' => "Không thể bỏ định dạng {$name} vì phim còn suất chiếu tương lai đang sử dụng định dạng này.",
                ]);
            }

            $locked->update($attributes);
            $locked->genres()->sync($genreIds);
            $locked->supportedPresentationFormats()->sync($formatIds);

            return $locked->fresh(['genres', 'supportedPresentationFormats']);
        });
    }

    /** @param list<int> $formatIds */
    private function lockedFormats(array $formatIds)
    {
        return PresentationFormat::query()->whereIn('id', $formatIds)->orderBy('id')->lockForUpdate()->get();
    }

    /** @param list<int> $requestedIds */
    private function assertKnownFormats(array $requestedIds, $formats): void
    {
        $knownIds = $formats->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if (array_diff($requestedIds, $knownIds) !== []) {
            throw ValidationException::withMessages([
                'presentation_format_ids' => 'Định dạng trình chiếu đã chọn không tồn tại.',
            ]);
        }
    }
}
