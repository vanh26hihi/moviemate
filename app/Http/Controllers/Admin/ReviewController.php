<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Review;
use App\Services\CinemaAccessService;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\View\View;

final class ReviewController extends Controller
{
    public function __construct(private readonly CinemaAccessService $access) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([Review::MODERATION_PENDING, Review::MODERATION_PUBLISHED, Review::MODERATION_HIDDEN, Review::MODERATION_REJECTED])],
            'movie_id' => ['nullable', 'integer'],
            'cinema_id' => ['nullable', 'integer'],
            'rating' => ['nullable', 'integer', 'between:1,10'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $ids = $this->access->accessibleCinemas($request->user())->pluck('id');
        $reviews = Review::query()->with(['movie', 'user', 'booking.cinema'])
            ->whereHas('booking', fn ($query) => $query->whereIn('cinema_id', $ids)
                ->when($filters['cinema_id'] ?? null, fn ($query, $cinemaId) => $query->where('cinema_id', $cinemaId)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('moderation_status', $status))
            ->when($filters['movie_id'] ?? null, fn ($query, $movieId) => $query->where('movie_id', $movieId))
            ->when($filters['rating'] ?? null, fn ($query, $rating) => $query->where('rating', $rating))
            ->when(trim((string) ($filters['search'] ?? '')), fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('movie', fn ($query) => $query->where('title', 'like', "%{$search}%"));
            }))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'filters' => $filters,
            'cinemas' => $this->access->accessibleCinemas($request->user()),
            'movies' => Movie::query()->whereIn('id', Review::query()->select('movie_id'))->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function moderate(Request $request, Review $review, ReviewService $reviews): RedirectResponse
    {
        $ids = $this->access->accessibleCinemas($request->user())->pluck('id');
        abort_unless($review->booking()->whereIn('cinema_id', $ids)->exists(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in([Review::MODERATION_PUBLISHED, Review::MODERATION_HIDDEN, Review::MODERATION_REJECTED])],
            'reason' => [new RequiredIf(in_array($request->string('status')->toString(), [Review::MODERATION_HIDDEN, Review::MODERATION_REJECTED], true)), 'nullable', 'string', 'max:1000'],
        ]);
        $reviews->moderate($review, $data['status'], $data['reason'] ?? null, $request->user()->id);

        return back()->with('success', 'Đã cập nhật trạng thái đánh giá.');
    }
}
