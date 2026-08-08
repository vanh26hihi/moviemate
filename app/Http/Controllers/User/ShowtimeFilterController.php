<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Movie;
use App\Services\CinemaContext;
use App\Services\CustomerShowtimeCatalogService;
use App\Services\PublicShowtimeCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ShowtimeFilterController extends Controller
{
    public function __construct(
        private readonly PublicShowtimeCatalog $catalog,
        private readonly CustomerShowtimeCatalogService $customerCatalog,
        private readonly CinemaContext $context,
    ) {}

    public function __invoke(Request $request): View
    {
        $data = $request->validate([
            'context' => ['required', Rule::in(['cinema', 'movie', 'home'])],
            'cinema' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9-]+$/'],
            'movie' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $cinema = empty($data['cinema']) ? null : Cinema::query()->active()->where('code', mb_strtoupper($data['cinema']))->firstOrFail();
        $movie = empty($data['movie']) ? null : Movie::query()->whereIn('status', PublicShowtimeCatalog::MOVIE_STATUSES)
            ->where('slug', $data['movie'])->firstOrFail();
        abort_if($data['context'] === 'cinema' && ! $cinema, 422);
        abort_if($data['context'] === 'movie' && ! $movie, 422);
        $date = $this->catalog->date($data['date'] ?? null, $cinema);
        $showtimes = $this->customerCatalog->forDate($date, $cinema, $movie);
        $preferred = $this->context->preference();
        if (! $cinema && $preferred) {
            $showtimes = $showtimes->sortBy(fn ($showtime): array => [
                (int) $showtime['cinema']->id === (int) $preferred->id ? 0 : 1,
                $showtime['cinema']->name,
                $showtime['starts_at'],
            ])->values();
        }

        return view('user.partials.showtime-results', [
            'showtimes' => $showtimes,
            'selectedDate' => $date,
            'context' => $data['context'],
            'cinema' => $cinema,
            'movie' => $movie,
        ]);
    }
}
