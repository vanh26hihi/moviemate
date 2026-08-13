<?php

namespace App\Services\Admin;

use App\Exceptions\ShowtimeScheduleConfigurationException;
use App\Models\Booking;
use App\Models\Cinema;
use App\Models\CinemaOperatingHour;
use App\Models\Payment;
use App\Models\SeatIncidentImpact;
use App\Models\SeatIncidentResolution;
use App\Models\Showtime;
use App\Models\User;
use App\Services\CinemaAccessService;
use App\Services\Seats\SeatIncidentImpactClassifier;
use App\Services\ShowtimeLifecycleService;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class Branch360ReadModel
{
    public const PRESENTATION_LIMIT = 8;

    private const PRIORITY_REFUND = 10;

    private const PRIORITY_REPLACEMENT_PRINT = 20;

    private const PRIORITY_PAID_IMPACT = 30;

    private const PRIORITY_PAYMENT = 40;

    private const PRIORITY_UPCOMING_IMPACT = 50;

    private const PRIORITY_INACTIVE_ROOM = 60;

    private const PRIORITY_CLOSED_DAY = 70;

    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly ShowtimeLifecycleService $lifecycle,
        private readonly ShowtimeScheduleService $schedule,
        private readonly SeatIncidentImpactClassifier $incidentClassifier,
    ) {}

    /**
     * @return array{
     *   header: array<string, mixed>,
     *   actionQueue: array{items:list<array<string, mixed>>,total:int,remaining:int,limit:int}
     * }
     */
    public function snapshot(Cinema $cinema, User $actor): array
    {
        $this->cinemaAccess->authorizeCinema($actor, (int) $cinema->id);

        $timezone = $this->timezone($cinema);
        $localNow = CarbonImmutable::now($timezone);
        $hours = CinemaOperatingHour::query()
            ->where('cinema_id', $cinema->id)
            ->where('day_of_week', $localNow->dayOfWeekIso)
            ->first();
        $upcoming = $this->upcomingShowtimes($cinema);
        $tasks = [
            ...$this->incidentTasks($cinema, $actor, $upcoming, $timezone),
            ...$this->paymentTasks($cinema, $actor, $timezone),
            ...$this->inactiveRoomTasks($actor, $upcoming),
            ...$this->closedDayTasks($cinema, $actor, $hours, $localNow),
        ];
        usort($tasks, fn (array $left, array $right): int => $this->compareTasks($left, $right));

        $total = count($tasks);
        $items = array_slice($tasks, 0, self::PRESENTATION_LIMIT);

        return [
            'header' => [
                'name' => (string) $cinema->name,
                'code' => (string) $cinema->code,
                'localTime' => $localNow,
                'generatedAt' => $localNow,
                'timezone' => $timezone,
                'branchStatus' => $this->branchStatus($cinema),
                'operatingHours' => $this->operatingHours($hours),
                'shortAddress' => $this->shortAddress((string) $cinema->address),
            ],
            'actionQueue' => [
                'items' => $items,
                'total' => $total,
                'remaining' => max(0, $total - count($items)),
                'limit' => self::PRESENTATION_LIMIT,
            ],
        ];
    }

    /** @return Collection<int, Showtime> */
    private function upcomingShowtimes(Cinema $cinema): Collection
    {
        $query = Showtime::query()->where('showtimes.cinema_id', $cinema->id);
        $this->lifecycle->applyFilter($query, ShowtimeLifecycleService::UPCOMING);

        return $query->with([
            'movie:id,title,duration',
            'room:id,cinema_id,code,name,status,cleaning_buffer_minutes',
            'room.cinema:id,timezone,default_cleaning_buffer_minutes,status,archived_at',
        ])->orderBy('showtimes.show_date')->orderBy('showtimes.show_time')->orderBy('showtimes.id')->get();
    }

    /**
     * @param  Collection<int, Showtime>  $upcoming
     * @return list<array<string, mixed>>
     */
    private function incidentTasks(Cinema $cinema, User $actor, Collection $upcoming, string $timezone): array
    {
        if (! $actor->hasPermission('seats.maintenance.view') || $upcoming->isEmpty()) {
            return [];
        }

        $impacts = SeatIncidentImpact::query()
            ->where('resolution_status', SeatIncidentImpact::RESOLUTION_UNRESOLVED)
            ->whereHas('incident', fn ($query) => $query
                ->where('cinema_id', $cinema->id)
                ->where('status', 'open'))
            ->whereHas('bookingSeat', fn ($query) => $query->whereIn('showtime_id', $upcoming->pluck('id')))
            ->with([
                'incident:id,cinema_id,room_id,status',
                'bookingSeat:id,booking_id,showtime_id,seat_id',
                'bookingSeat.booking:id,booking_code,booking_status,payment_status',
                'bookingSeat.booking.payments:id,booking_id,provider,status,verified_at,settled_at,settled_by_user_id',
                'bookingSeat.showtime:id,movie_id,cinema_id,room_id,show_date,show_time,status',
                'bookingSeat.showtime.movie:id,title,duration',
                'bookingSeat.showtime.room:id,cinema_id,code,name,cleaning_buffer_minutes',
                'bookingSeat.showtime.room.cinema:id,timezone,default_cleaning_buffer_minutes,status,archived_at',
                'resolution',
            ])
            ->orderBy('id')
            ->get();

        $projected = [];
        foreach ($impacts as $impact) {
            $bookingSeat = $impact->bookingSeat;
            $booking = $bookingSeat?->booking;
            $showtime = $bookingSeat?->showtime;
            $incident = $impact->incident;
            if (! $booking || ! $showtime || ! $incident) {
                continue;
            }

            $relevantAt = $this->schedule->windowFor($showtime)->start->setTimezone($timezone);
            $resolution = $impact->resolution;
            $classification = $this->incidentClassifier->classify($booking);
            $task = match (true) {
                $resolution?->resolution_type === SeatIncidentResolution::TYPE_REQUIRES_REFUND => $this->task(
                    'incident_requires_refund',
                    self::PRIORITY_REFUND,
                    'Khẩn cấp',
                    "Đơn {$booking->booking_code} cần xử lý hoàn tiền do sự cố ghế.",
                    $relevantAt,
                    'Xử lý sự cố',
                    route('admin.rooms.seat-incidents.show', [$incident->room_id, $incident->id]),
                    $this->incidentContext($impact, $booking, $showtime),
                ),
                $resolution?->reprint_required
                    && $resolution->reprint_satisfied_at === null => $this->task(
                        'incident_replacement_print',
                        self::PRIORITY_REPLACEMENT_PRINT,
                        'Khẩn cấp',
                        "Đơn {$booking->booking_code} cần in vé thay thế sau chuyển ghế.",
                        $relevantAt,
                        'Mở sự cố',
                        route('admin.rooms.seat-incidents.show', [$incident->room_id, $incident->id]),
                        $this->incidentContext($impact, $booking, $showtime),
                    ),
                $classification === SeatIncidentImpact::PAID => $this->task(
                    'incident_paid_impact',
                    self::PRIORITY_PAID_IMPACT,
                    'Ưu tiên cao',
                    "Đơn {$booking->booking_code} đã thanh toán có ghế cần bố trí lại.",
                    $relevantAt,
                    'Bố trí lại ghế',
                    route('admin.rooms.seat-incidents.show', [$incident->room_id, $incident->id]),
                    $this->incidentContext($impact, $booking, $showtime),
                ),
                default => $this->task(
                    'incident_upcoming_impact',
                    self::PRIORITY_UPCOMING_IMPACT,
                    'Cần xử lý',
                    sprintf('Suất %s tại phòng %s có ghế gặp sự cố cần xử lý.', $relevantAt->format('H:i d/m'), $showtime->room?->code),
                    $relevantAt,
                    'Mở sự cố',
                    route('admin.rooms.seat-incidents.show', [$incident->room_id, $incident->id]),
                    $this->incidentContext($impact, $booking, $showtime),
                ),
            };

            $businessKey = $incident->id.':'.$booking->id;
            if (! isset($projected[$businessKey]) || $this->compareTasks($task, $projected[$businessKey]) < 0) {
                $projected[$businessKey] = $task;
            }
        }

        return array_values($projected);
    }

    /** @return list<array<string, mixed>> */
    private function paymentTasks(Cinema $cinema, User $actor, string $timezone): array
    {
        if (! $actor->hasPermission('payments.reconcile')) {
            return [];
        }

        return Payment::query()
            ->whereIn('status', [Payment::STATUS_UNRESOLVED, Payment::STATUS_REVIEW])
            ->whereHas('booking', fn ($query) => $query->where('cinema_id', $cinema->id))
            ->with(['booking:id,cinema_id,showtime_id,booking_code'])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get()
            ->map(function (Payment $payment) use ($timezone): array {
                $type = $payment->status === Payment::STATUS_REVIEW ? 'payment_review' : 'payment_unresolved';
                $message = $payment->status === Payment::STATUS_REVIEW
                    ? "Thanh toán của đơn {$payment->booking->booking_code} cần được xem xét."
                    : "Thanh toán của đơn {$payment->booking->booking_code} đang chờ đối soát.";

                return $this->task(
                    $type,
                    self::PRIORITY_PAYMENT,
                    'Ưu tiên cao',
                    $message,
                    CarbonImmutable::instance($payment->updated_at)->setTimezone($timezone),
                    'Mở đối soát',
                    route('admin.payment-reconciliation.index'),
                    [
                        'paymentId' => (int) $payment->id,
                        'bookingCode' => (string) $payment->booking->booking_code,
                    ],
                );
            })->all();
    }

    /**
     * @param  Collection<int, Showtime>  $upcoming
     * @return list<array<string, mixed>>
     */
    private function inactiveRoomTasks(User $actor, Collection $upcoming): array
    {
        if (! $actor->hasPermission('rooms.view')) {
            return [];
        }

        return $upcoming->filter(fn (Showtime $showtime): bool => $showtime->room?->status === 'inactive')
            ->groupBy('room_id')
            ->map(function (Collection $showtimes): array {
                /** @var Showtime $showtime */
                $showtime = $showtimes->sortBy(fn (Showtime $item) => $this->schedule->windowFor($item)->start)->first();
                $room = $showtime->room;
                $startsAt = $this->schedule->windowFor($showtime)->start;

                return $this->task(
                    'inactive_room_future_show',
                    self::PRIORITY_INACTIVE_ROOM,
                    'Cần xử lý',
                    "Phòng {$room->code} đang ngừng hoạt động nhưng còn suất chiếu tương lai.",
                    $startsAt,
                    'Mở phòng chiếu',
                    route('admin.rooms.show', $room->id),
                    [
                        'roomId' => (int) $room->id,
                        'roomCode' => (string) $room->code,
                        'showtimeId' => (int) $showtime->id,
                    ],
                );
            })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function closedDayTasks(
        Cinema $cinema,
        User $actor,
        ?CinemaOperatingHour $hours,
        CarbonImmutable $localNow,
    ): array {
        if (! $hours?->is_closed || ! $actor->hasPermission('showtimes.view')) {
            return [];
        }

        $showtimes = Showtime::query()
            ->where('cinema_id', $cinema->id)
            ->whereDate('show_date', $localNow->toDateString())
            ->where('status', 'active')
            ->with(['movie:id,title,duration', 'room:id,cinema_id,code,name,cleaning_buffer_minutes',
                'room.cinema:id,timezone,default_cleaning_buffer_minutes,status,archived_at'])
            ->orderBy('show_time')
            ->orderBy('id')
            ->get()
            ->filter(fn (Showtime $showtime): bool => in_array(
                $this->lifecycle->state($showtime, $localNow),
                [ShowtimeLifecycleService::UPCOMING, ShowtimeLifecycleService::PLAYING],
                true,
            ));
        if ($showtimes->isEmpty()) {
            return [];
        }

        /** @var Showtime $nearest */
        $nearest = $showtimes->first();
        $relevantAt = $this->schedule->windowFor($nearest)->start;

        return [$this->task(
            'closed_day_schedule_conflict',
            self::PRIORITY_CLOSED_DAY,
            'Cần xử lý',
            sprintf('Chi nhánh đóng cửa hôm nay nhưng còn %d suất chiếu đang hoặc sắp diễn ra.', $showtimes->count()),
            $relevantAt,
            'Mở lịch chiếu',
            route('admin.showtimes.index', ['show_date' => $localNow->toDateString()]),
            [
                'showtimeId' => (int) $nearest->id,
                'businessDate' => $localNow->toDateString(),
                'showtimeCount' => $showtimes->count(),
            ],
        )];
    }

    /** @return array<string, string> */
    private function branchStatus(Cinema $cinema): array
    {
        return match (true) {
            $cinema->archived_at !== null => ['key' => 'archived', 'label' => 'Đã lưu trữ'],
            $cinema->status === 'active' => ['key' => 'active', 'label' => 'Đang hoạt động'],
            default => ['key' => 'inactive', 'label' => 'Ngừng hoạt động'],
        };
    }

    /** @return array{key:string,label:string,detail:?string} */
    private function operatingHours(?CinemaOperatingHour $hours): array
    {
        if ($hours === null) {
            return [
                'key' => 'not_configured',
                'label' => 'Chưa cấu hình giờ hoạt động',
                'detail' => null,
            ];
        }
        if ($hours->is_closed) {
            return ['key' => 'closed', 'label' => 'Đóng cửa hôm nay', 'detail' => null];
        }

        $opensAt = $hours->opens_at ? substr((string) $hours->opens_at, 0, 5) : '—';
        $latestStart = $hours->latest_show_start_at ? substr((string) $hours->latest_show_start_at, 0, 5) : '—';

        return [
            'key' => 'configured',
            'label' => 'Hoạt động hôm nay',
            'detail' => "Mở cửa {$opensAt} · Nhận suất chiếu cuối {$latestStart}",
        ];
    }

    private function timezone(Cinema $cinema): string
    {
        $timezone = $cinema->timezone ?? config('cinema.timezone');
        if (! is_string($timezone) || trim($timezone) === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new ShowtimeScheduleConfigurationException('Timezone nghiệp vụ của rạp không hợp lệ.');
        }

        return $timezone;
    }

    private function shortAddress(string $address): string
    {
        return mb_strlen($address) <= 120 ? $address : mb_substr($address, 0, 119).'…';
    }

    /** @return array<string, int|string|null> */
    private function incidentContext(SeatIncidentImpact $impact, Booking $booking, Showtime $showtime): array
    {
        return [
            'incidentId' => (int) $impact->seat_incident_id,
            'impactId' => (int) $impact->id,
            'bookingCode' => (string) $booking->booking_code,
            'showtimeId' => (int) $showtime->id,
            'roomId' => (int) $showtime->room_id,
            'roomCode' => (string) $showtime->room?->code,
            'movie' => (string) $showtime->movie?->title,
        ];
    }

    /** @return array<string, mixed> */
    private function task(
        string $type,
        int $priorityRank,
        string $priority,
        string $message,
        CarbonImmutable $relevantAt,
        string $actionLabel,
        string $actionUrl,
        array $context,
    ): array {
        $entityId = $context['impactId'] ?? $context['paymentId'] ?? $context['roomId'] ?? $context['showtimeId'] ?? 0;

        return [
            'key' => $type.':'.$entityId,
            'type' => $type,
            'priority' => $priority,
            'priorityRank' => $priorityRank,
            'message' => $message,
            'relevantAt' => $relevantAt,
            'actionLabel' => $actionLabel,
            'actionUrl' => $actionUrl,
            'context' => $context,
        ];
    }

    private function compareTasks(array $left, array $right): int
    {
        return [$left['priorityRank'], $left['relevantAt']->getTimestamp(), $left['key']]
            <=> [$right['priorityRank'], $right['relevantAt']->getTimestamp(), $right['key']];
    }
}
