<?php

namespace App\Services\Admin;

use App\Exceptions\ShowtimeScheduleException;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\User;
use App\Services\CinemaAccessService;
use App\Services\ShowtimeLifecycleService;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ShowtimeOperationsBoard
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly ShowtimeScheduleService $schedule,
        private readonly ShowtimeLifecycleService $lifecycle,
    ) {}

    /** @param array<string, mixed> $filters */
    public function build(User $user, array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query = Showtime::query()
            ->with([
                'movie:id,title,slug,duration,status',
                'cinema:id,code,name,timezone',
                'room:id,cinema_id,code,name,cleaning_buffer_minutes,status',
                'room.cinema:id,code,name,timezone,default_cleaning_buffer_minutes',
                'presentationFormat:id,code,name,is_active',
            ])
            ->withCount([
                'bookings as bookings_count',
                'bookings as paid_bookings_count' => fn (Builder $query): Builder => $query->where('payment_status', 'paid'),
            ])
            ->whereDate('show_date', '>=', $from->toDateString())
            ->whereDate('show_date', '<=', $to->toDateString());

        $this->cinemaAccess->scope($query, $user, 'showtimes.cinema_id');
        $this->applyFilters($query, $filters);

        $showtimes = $query
            ->orderBy('showtimes.show_date')
            ->orderBy('showtimes.show_time')
            ->orderBy('showtimes.room_id')
            ->get();

        $entries = $showtimes->map(fn (Showtime $showtime): array => $this->entry($showtime));
        $rooms = $this->rooms($user, $showtimes, $filters);
        $days = $this->days($from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'rooms' => $rooms,
            'entries' => $entries,
            'entriesByDay' => $entries->groupBy('date'),
            'entriesByRoomAndDay' => $entries->groupBy(fn (array $entry): string => $entry['room_id'].'|'.$entry['date']),
            'summary' => $this->summary($entries, $rooms, $days),
            'movies' => Movie::query()->whereIn('id', $showtimes->pluck('movie_id')->unique())->orderBy('title')->get(['id', 'title']),
            'formats' => PresentationFormat::query()->whereIn('id', $showtimes->pluck('presentation_format_id')->filter()->unique())->orderBy('sort_order')->get(['id', 'code', 'name']),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['room_id', 'movie_id', 'presentation_format_id'] as $column) {
            if (! empty($filters[$column])) {
                $query->where('showtimes.'.$column, (int) $filters[$column]);
            }
        }

        if (! empty($filters['status'])) {
            $query->where('showtimes.status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $needle = str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['search']);
            $query->where(function (Builder $query) use ($needle): void {
                $query->whereHas('movie', fn (Builder $movie): Builder => $movie->where('title', 'like', "%{$needle}%"))
                    ->orWhereHas('room', fn (Builder $room): Builder => $room
                        ->where('code', 'like', "%{$needle}%")
                        ->orWhere('name', 'like', "%{$needle}%"));
            });
        }
    }

    private function entry(Showtime $showtime): array
    {
        try {
            $window = $this->schedule->windowFor($showtime);
            $lifecycle = $this->lifecycle->snapshot($showtime);

            return [
                'id' => (int) $showtime->id,
                'date' => $window->start->toDateString(),
                'room_id' => (int) $showtime->room_id,
                'cinema_id' => (int) $showtime->cinema_id,
                'movie' => $showtime->movie->title,
                'room' => $showtime->room->code.' · '.$showtime->room->name,
                'cinema' => $showtime->cinema?->name ?? $showtime->room->cinema?->name,
                'format' => $showtime->presentationFormat?->name ?? 'Chưa xác định',
                'starts_at' => $window->start,
                'movie_ends_at' => $window->movieEnd,
                'room_ready_at' => $window->operationalEnd,
                'lifecycle' => $lifecycle['state'],
                'lifecycle_label' => $lifecycle['label'],
                'status' => $showtime->status,
                'bookings_count' => (int) $showtime->bookings_count,
                'paid_bookings_count' => (int) $showtime->paid_bookings_count,
                'invalid' => false,
            ];
        } catch (ShowtimeScheduleException $exception) {
            $start = CarbonImmutable::parse($showtime->show_date.' '.$showtime->show_time, $this->schedule->timezone($showtime->room));

            return [
                'id' => (int) $showtime->id,
                'date' => $showtime->show_date->toDateString(),
                'room_id' => (int) $showtime->room_id,
                'cinema_id' => (int) $showtime->cinema_id,
                'movie' => $showtime->movie->title,
                'room' => $showtime->room->code.' · '.$showtime->room->name,
                'cinema' => $showtime->cinema?->name ?? $showtime->room->cinema?->name,
                'format' => $showtime->presentationFormat?->name ?? 'Chưa xác định',
                'starts_at' => $start,
                'movie_ends_at' => null,
                'room_ready_at' => null,
                'lifecycle' => 'invalid',
                'lifecycle_label' => 'Cần kiểm tra',
                'status' => $showtime->status,
                'bookings_count' => (int) $showtime->bookings_count,
                'paid_bookings_count' => (int) $showtime->paid_bookings_count,
                'invalid' => true,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function days(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $days = collect();
        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $days->push($day);
        }

        return $days;
    }

    /** @param array<string, mixed> $filters */
    private function rooms(User $user, Collection $showtimes, array $filters): Collection
    {
        $query = Room::query()->with('cinema:id,code,name')->orderBy('cinema_id')->orderBy('code');
        $this->cinemaAccess->scope($query, $user, 'rooms.cinema_id');

        if (! empty($filters['room_id'])) {
            $query->whereKey((int) $filters['room_id']);
        } elseif ($showtimes->isNotEmpty()) {
            $query->whereIn('id', $showtimes->pluck('room_id')->unique());
        }

        return $query->get(['id', 'cinema_id', 'code', 'name', 'status']);
    }

    private function summary(Collection $entries, Collection $rooms, Collection $days): array
    {
        $active = $entries->where('status', 'active');
        $occupiedMinutes = $active->sum(function (array $entry): int {
            return $entry['room_ready_at'] ? $entry['starts_at']->diffInMinutes($entry['room_ready_at']) : 0;
        });
        $roomDays = max(1, $rooms->count() * $days->count());

        return [
            'total' => $entries->count(),
            'active' => $active->count(),
            'cancelled' => $entries->where('status', 'cancelled')->count(),
            'playing' => $entries->where('lifecycle', 'playing')->count(),
            'invalid' => $entries->where('invalid', true)->count(),
            'bookings' => $entries->sum('bookings_count'),
            'paid_bookings' => $entries->sum('paid_bookings_count'),
            'occupied_minutes' => $occupiedMinutes,
            'average_showtimes_per_room_day' => round($active->count() / $roomDays, 1),
        ];
    }
}
