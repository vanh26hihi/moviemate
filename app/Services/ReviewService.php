<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final class ReviewService
{
    public function __construct(private readonly ActivityLogger $activities) {}

    public function eligibleBooking(User $user, int $movieId, ?int $bookingId = null): Booking
    {
        $booking = Booking::query()->with('showtime.movie')
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->where('booking_status', 'paid')
            ->whereHas('showtime', fn ($q) => $q->where('movie_id', $movieId))
            ->when($bookingId, fn ($q) => $q->whereKey($bookingId))
            ->latest('paid_at')
            ->first();
        if (! $booking) {
            throw ValidationException::withMessages(['review' => 'Bạn chỉ có thể đánh giá phim từ một đơn đã thanh toán.']);
        }
        $showtime = $booking->showtime;
        $ends = CarbonImmutable::parse($showtime->show_date->toDateString().' '.$showtime->show_time, config('cinema.timezone', 'Asia/Ho_Chi_Minh'))->addMinutes((int) $showtime->movie->duration);
        if ($ends->isFuture()) {
            throw ValidationException::withMessages(['review' => 'Bạn có thể đánh giá sau khi phim kết thúc.']);
        }

        return $booking;
    }

    public function save(User $user, int $movieId, int $rating, ?string $comment, ?int $bookingId = null): Review
    {
        $comment = trim((string) $comment);
        if ($comment !== strip_tags($comment)) {
            throw ValidationException::withMessages(['comment' => 'Nội dung đánh giá không được chứa mã HTML.']);
        }
        $booking = $this->eligibleBooking($user, $movieId, $bookingId);
        if (Review::query()->where('user_id', $user->id)->where('movie_id', $movieId)->exists()) {
            throw ValidationException::withMessages(['review' => 'Bạn đã đánh giá phim này.']);
        }
        $flags = $this->flags($comment);
        $published = $flags === [];
        $attributes = [
            'booking_id' => $booking->id, 'rating' => $rating, 'comment' => $comment ?: null, 'is_verified' => true,
            'status' => $published ? Review::STATUS_VISIBLE : Review::STATUS_HIDDEN,
            'moderation_status' => $published ? Review::MODERATION_PUBLISHED : Review::MODERATION_PENDING, 'moderation_flags' => $flags,
            'first_published_at' => $published ? now() : null,
        ];
        try {
            $review = Review::query()->create(['user_id' => $user->id, 'movie_id' => $movieId] + $attributes);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['review' => 'Bạn đã đánh giá phim này.']);
        }

        return $review;
    }

    public function moderate(Review $review, string $status, ?string $reason, int $moderatorId): void
    {
        $previousStatus = $review->moderation_status;
        $review->update(['moderation_status' => $status, 'status' => $status === Review::MODERATION_PUBLISHED ? Review::STATUS_VISIBLE : Review::STATUS_HIDDEN, 'moderation_reason' => trim((string) $reason) ?: null, 'moderated_by_user_id' => $moderatorId, 'moderated_at' => now(), 'first_published_at' => $status === Review::MODERATION_PUBLISHED ? ($review->first_published_at ?: now()) : $review->first_published_at]);
        $this->activities->log('review.moderated', $review, ['moderation_status' => $previousStatus], ['moderation_status' => $status], ['reason' => trim((string) $reason) ?: null]);
    }

    private function flags(string $comment): array
    {
        $flags = [];
        if (preg_match('~https?://|www\.|\b[a-z0-9][a-z0-9.-]*\.(?:com|net|org|vn|io|dev)(?:/|\b)~iu', $comment)) {
            $flags[] = 'url';
        }
        if (preg_match('/(.)\1{7,}/u', $comment)) {
            $flags[] = 'spam';
        }
        if (mb_strlen($comment) > 1500) {
            $flags[] = 'excessive';
        }
        if (preg_match('/\b(?:địt|đụ|fuck|shit)\b/iu', $comment)) {
            $flags[] = 'profanity';
        }

        return $flags;
    }
}
