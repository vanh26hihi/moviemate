<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\User;
use App\Services\CinemaAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class UserDetailReadModel
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    /** @return array<string, mixed> */
    public function build(User $actor, User $target, array $filters = []): array
    {
        $target->load([
            'role',
            'cinemaAssignments' => fn ($query) => $query
                ->with(['cinema', 'assignedBy'])
                ->latest('assigned_at'),
        ]);

        $bookings = $this->visibleBookings($actor, $target);

        return [
            'managedUser' => $target,
            'summary' => $this->summary(clone $bookings, $target),
            'bookings' => $this->bookingHistory(clone $bookings, $filters),
            'activity' => $this->activity($target),
            'bookingStatuses' => Booking::STATUSES,
            'filters' => $filters,
        ];
    }

    private function visibleBookings(User $actor, User $target): Builder
    {
        $query = Booking::query()
            ->where('user_id', $target->id)
            ->with([
                'cinema:id,name,code',
                'showtime:id,movie_id,room_id,show_date,show_time',
                'showtime.movie:id,title',
                'showtime.room:id,name',
                'authoritativePayment' => fn ($query) => $query->select([
                    'payments.id',
                    'payments.booking_id',
                    'payments.amount',
                    'payments.provider',
                    'payments.verified_at',
                    'payments.settled_at',
                ]),
            ]);

        return $this->cinemaAccess->scope($query, $actor, 'bookings.cinema_id');
    }

    /** @return array<string, int|string|null> */
    private function summary(Builder $bookings, User $target): array
    {
        $aggregate = (clone $bookings)
            ->selectRaw('COUNT(*) AS booking_count')
            ->selectRaw("SUM(CASE WHEN booking_status = 'paid' THEN 1 ELSE 0 END) AS paid_count")
            ->selectRaw("SUM(CASE WHEN booking_status IN ('cancelled', 'expired') THEN 1 ELSE 0 END) AS unsuccessful_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN booking_status = 'paid' THEN total_amount ELSE 0 END), 0) AS paid_value")
            ->selectRaw('MAX(created_at) AS latest_booking_at')
            ->toBase()
            ->first();

        $reviewAggregate = $target->reviews()
            ->selectRaw('COUNT(*) AS review_count')
            ->selectRaw('COALESCE(AVG(rating), 0) AS average_rating')
            ->toBase()
            ->first();

        return [
            'booking_count' => (int) ($aggregate?->booking_count ?? 0),
            'paid_count' => (int) ($aggregate?->paid_count ?? 0),
            'unsuccessful_count' => (int) ($aggregate?->unsuccessful_count ?? 0),
            'paid_value' => (int) ($aggregate?->paid_value ?? 0),
            'latest_booking_at' => $aggregate?->latest_booking_at,
            'review_count' => (int) ($reviewAggregate?->review_count ?? 0),
            'average_rating' => round((float) ($reviewAggregate?->average_rating ?? 0), 1),
        ];
    }

    private function bookingHistory(Builder $query, array $filters): LengthAwarePaginator
    {
        return $query
            ->when($filters['booking_search'] ?? null, function (Builder $query, string $search): void {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search));
                $query->where(function (Builder $query) use ($escaped): void {
                    $query->where('booking_code', 'like', "%{$escaped}%")
                        ->orWhereHas('showtime.movie', fn (Builder $movie) => $movie->where('title', 'like', "%{$escaped}%"));
                });
            })
            ->when($filters['booking_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('booking_status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->latest('id')
            ->paginate(10, ['*'], 'bookings_page')
            ->withQueryString();
    }

    /** @return Collection<int, ActivityLog> */
    private function activity(User $target): Collection
    {
        return ActivityLog::query()
            ->with('actor:id,name')
            ->where(function (Builder $query) use ($target): void {
                $query->where('actor_user_id', $target->id)
                    ->orWhere(function (Builder $subject) use ($target): void {
                        $subject->where('subject_type', User::class)
                            ->where('subject_id', (string) $target->id);
                    });
            })
            ->latest('id')
            ->limit(12)
            ->get();
    }
}
