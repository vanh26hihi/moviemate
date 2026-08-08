<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTransaction;
use App\Models\Movie;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $reviews = Review::query()->where('user_id', $request->user()->id)->with(['movie', 'booking'])->latest()->paginate(15);
        $rewardPointsByReviewId = LoyaltyTransaction::query()
            ->whereIn('source_key', $reviews->getCollection()->map(fn (Review $review): string => 'review:'.$review->id.':published'))
            ->get(['source_key', 'points_delta'])
            ->mapWithKeys(fn (LoyaltyTransaction $transaction): array => [(int) str($transaction->source_key)->between('review:', ':published')->toString() => $transaction->points_delta]);

        return view('user.reviews.index', compact('reviews', 'rewardPointsByReviewId'));
    }

    public function store(Request $request, Movie $movie, ReviewService $reviews): RedirectResponse
    {
        $data = $request->validate(['booking_id' => ['nullable', 'integer', 'exists:bookings,id'], 'rating' => ['required', 'integer', 'between:1,10'], 'comment' => ['nullable', 'string', 'max:2000']]);
        $review = $reviews->save($request->user(), $movie->id, $data['rating'], $data['comment'] ?? null, $data['booking_id'] ?? null);

        return back()->with('success', $review->moderation_status === Review::MODERATION_PENDING ? 'Đánh giá đang chờ kiểm duyệt.' : 'Đã đăng đánh giá và cộng điểm thưởng.');
    }
}
