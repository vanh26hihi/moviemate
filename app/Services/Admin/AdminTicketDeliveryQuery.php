<?php

namespace App\Services\Admin;

use App\Models\BookingTicketDelivery;
use App\Services\CinemaAccessService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminTicketDeliveryQuery
{
    private const CACHE_KEY = 'admin:ticket-deliveries:attention:v1';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly CinemaAccessService $cinemaAccess,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = BookingTicketDelivery::query()
            ->select(['id', 'booking_id', 'status', 'attempts', 'available_at', 'processing_started_at', 'lease_expires_at', 'sent_at', 'last_error_code', 'created_at', 'updated_at'])
            ->with(['booking:id,user_id,cinema_id,booking_code,customer_email,booking_status,payment_status', 'booking.user:id,email'])
            ->whereHas('booking', function (Builder $query): void {
                $this->scopeToActor($query);
            })
            ->when($filters['booking_code'] ?? null, fn (Builder $query, string $value) => $query
                ->whereHas('booking', fn (Builder $booking) => $booking->where('booking_code', 'like', '%'.$this->escapeLike($value).'%')))
            ->when($filters['recipient'] ?? null, function (Builder $query, string $value): void {
                $like = '%'.$this->escapeLike($value).'%';
                $query->whereHas('booking', fn (Builder $booking) => $booking
                    ->where('customer_email', 'like', $like)
                    ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(isset($filters['attempts_min']), fn (Builder $query) => $query->where('attempts', '>=', (int) $filters['attempts_min']))
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['sent_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('sent_at', '>=', $date))
            ->when($filters['sent_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('sent_at', '<=', $date))
            ->when(($filters['has_error'] ?? null) === 'yes', fn (Builder $query) => $query->whereNotNull('last_error_code'))
            ->when(($filters['has_error'] ?? null) === 'no', fn (Builder $query) => $query->whereNull('last_error_code'))
            ->when(($filters['retry_due'] ?? null) === 'yes', fn (Builder $query) => $this->retryDue($query))
            ->when(($filters['retry_due'] ?? null) === 'no', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('available_at')->orWhere('available_at', '>', now())))
            ->when(($filters['stale_claim'] ?? null) === 'yes', fn (Builder $query) => $query
                ->where('status', BookingTicketDelivery::STATUS_PROCESSING)->where('lease_expires_at', '<=', now()))
            ->when(($filters['stale_claim'] ?? null) === 'no', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('status', '!=', BookingTicketDelivery::STATUS_PROCESSING)
                    ->orWhereNull('lease_expires_at')->orWhere('lease_expires_at', '>', now())));

        return $query->orderBy($filters['sort'] ?? 'updated_at', $filters['direction'] ?? 'desc')
            ->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
    }

    public function badgeLabel(): ?string
    {
        $count = $this->attentionQuery()->count();

        return $count === 0 ? null : ($count > 99 ? '99+' : (string) $count);
    }

    public function forgetBadge(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Console commands and queued work run without an authenticated actor. Those callers are
     * already trusted and must see every branch, so only scope real HTTP actors.
     */
    private function scopeToActor(Builder $query): void
    {
        if ($actor = auth()->user()) {
            $this->cinemaAccess->scope($query, $actor, 'bookings.cinema_id');
        }
    }

    public function attentionQuery(): Builder
    {
        return BookingTicketDelivery::query()
            ->whereHas('booking', function (Builder $query): void {
                $this->scopeToActor($query);
            })->where(function (Builder $query): void {
                $query->where('status', BookingTicketDelivery::STATUS_FAILED)
                    ->orWhere(fn (Builder $query) => $this->retryDue($query))
                    ->orWhere(fn (Builder $query) => $query->where('status', BookingTicketDelivery::STATUS_PROCESSING)
                        ->where('lease_expires_at', '<=', now()));
            });
    }

    private function retryDue(Builder $query): Builder
    {
        return $query->whereIn('status', [BookingTicketDelivery::STATUS_PENDING, BookingTicketDelivery::STATUS_FAILED])
            ->where(fn (Builder $query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()));
    }

    private function escapeLike(string $value): string
    {
        return addcslashes(trim($value), '%_\\');
    }
}
