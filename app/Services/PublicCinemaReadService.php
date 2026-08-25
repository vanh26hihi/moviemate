<?php

namespace App\Services;

use App\Models\Cinema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PublicCinemaReadService
{
    public const MAX_RESULTS = 12;

    /** @return Collection<int, array<string, mixed>> */
    public function list(array $filters = [], int $limit = self::MAX_RESULTS): Collection
    {
        $limit = max(1, min(self::MAX_RESULTS, $limit));
        $query = Cinema::query()->active()->with('operatingHours');

        if ($search = $this->plainText($filters['query'] ?? null, 100)) {
            $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('district', 'like', "%{$search}%"));
        }
        foreach (['city', 'district'] as $field) {
            if ($value = $this->plainText($filters[$field] ?? null, 120)) {
                $query->where($field, $value);
            }
        }

        return $query->orderBy('city')->orderBy('name')->limit($limit)->get()
            ->map(fn (Cinema $cinema): array => [
                'code' => $cinema->code,
                'name' => $cinema->name,
                'address' => $cinema->address,
                'city' => $cinema->city,
                'district' => $cinema->district,
                'phone' => $cinema->phone,
                'description' => $cinema->description,
                'image_url' => $cinema->image ? asset('storage/'.ltrim($cinema->image, '/')) : null,
                'latitude' => $cinema->latitude === null ? null : (string) $cinema->latitude,
                'longitude' => $cinema->longitude === null ? null : (string) $cinema->longitude,
                'details_url' => route('cinemas.show', $cinema->code),
                'operating_hours' => $cinema->operatingHours->sortBy('day_of_week')->map(fn ($hours): array => [
                    'day_of_week' => (int) $hours->day_of_week,
                    'closed' => (bool) $hours->is_closed,
                    'opens_at' => $hours->is_closed ? null : substr((string) $hours->opens_at, 0, 5),
                    'latest_show_start_at' => $hours->is_closed ? null : substr((string) $hours->latest_show_start_at, 0, 5),
                ])->values()->all(),
            ]);
    }

    private function plainText(mixed $value, int $max): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(str_replace(['%', '_'], '', trim($value)), 0, $max);
    }
}
