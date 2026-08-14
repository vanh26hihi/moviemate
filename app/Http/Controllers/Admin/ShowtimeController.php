<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PricingConfigurationException;
use App\Exceptions\ShowtimeScheduleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShowtimeRequest;
use App\Http\Requests\Admin\UpdateShowtimeRequest;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use App\Services\ShowtimeLifecycleService;
use App\Services\ShowtimeScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShowtimeController extends Controller
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly ShowtimeScheduleService $schedule,
        private readonly ShowtimeLifecycleService $lifecycle,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request)
    {
        $query = Showtime::query()
            ->with(['movie', 'cinema', 'room.cinema', 'roomLayout', 'presentationFormat', 'ticketPrices.seatType'])
            ->withExists(['bookings', 'bookingSeats']);
        $this->cinemaAccess->scope($query, $request->user(), 'showtimes.cinema_id');

        if ($movieId = $request->query('movie_id')) {
            $query->where('showtimes.movie_id', $movieId);
        }
        if ($state = $request->query('lifecycle')) {
            $this->lifecycle->applyFilter($query, $state);
        }
        if ($date = $request->query('show_date')) {
            // This is only an administrator display filter. Conflict detection uses complete intervals in the service.
            $query->whereDate('showtimes.show_date', $date);
        }

        $showtimes = $query->orderByDesc('showtimes.show_date')->orderBy('showtimes.show_time')->paginate(15)->withQueryString();
        $scheduleWindows = $showtimes->getCollection()->mapWithKeys(function (Showtime $showtime): array {
            try {
                return [$showtime->id => $this->schedule->windowFor($showtime)];
            } catch (ShowtimeScheduleException) {
                return [$showtime->id => null];
            }
        });
        $lifecycleSnapshots = $showtimes->getCollection()->mapWithKeys(function (Showtime $showtime): array {
            try {
                return [$showtime->id => $this->lifecycle->snapshot($showtime)];
            } catch (ShowtimeScheduleException) {
                return [$showtime->id => null];
            }
        });

        return view('admin.showtimes.index', [
            'showtimes' => $showtimes,
            'movies' => Movie::query()->orderBy('title')->get(),
            'scheduleWindows' => $scheduleWindows,
            'lifecycleSnapshots' => $lifecycleSnapshots,
            'cleaningBufferMinutes' => $this->schedule->cleaningBufferMinutes(),
            'cinemaTimezone' => $this->schedule->timezone(),
        ]);
    }

    public function show(Showtime $showtime)
    {
        $this->assertViewableShowtime($showtime);
        $showtime->load([
            'movie:id,title,duration,age_rating',
            'cinema:id,code,name,timezone,default_cleaning_buffer_minutes',
            'room:id,cinema_id,room_type_id,code,name,room_type,status,cleaning_buffer_minutes',
            'room.roomType:id,code,name',
            'room.cinema:id,code,name,timezone,default_cleaning_buffer_minutes',
            'roomLayout:id,room_id,version,name,status,published_at',
            'presentationFormat:id,code,name',
            'ticketPrices' => fn ($query) => $query->orderBy('seat_type_id'),
            'ticketPrices.seatType:id,code,name,is_pair,sort_order',
            'ticketPrices.priceBookVersion:id,version_number,status',
        ])->loadExists(['bookings', 'bookingSeats']);

        $lifecycle = $this->lifecycle->snapshot($showtime);
        $bookingCount = $showtime->bookings()->count();
        $recentBookings = $showtime->bookings()
            ->select([
                'id', 'showtime_id', 'booking_code', 'customer_name', 'sales_channel',
                'booking_status', 'payment_status', 'total_amount', 'currency', 'created_at',
            ])
            ->withCount('bookingSeats')
            ->latest('id')
            ->limit(10)
            ->get();
        $hasBookingHistory = $showtime->bookings_exists || $showtime->booking_seats_exists;
        $canEdit = $showtime->status === 'active'
            && $lifecycle['state'] === ShowtimeLifecycleService::UPCOMING
            && ! $hasBookingHistory;
        $canCancel = $showtime->status === 'active'
            && in_array($lifecycle['state'], [ShowtimeLifecycleService::UPCOMING, ShowtimeLifecycleService::PLAYING], true)
            && ! $hasBookingHistory;
        $roomState = match (true) {
            $lifecycle['state'] === ShowtimeLifecycleService::CANCELLED => [
                'key' => 'cancelled', 'label' => 'Không áp dụng — suất đã hủy',
            ],
            $lifecycle['state'] === ShowtimeLifecycleService::PLAYING => [
                'key' => 'playing', 'label' => 'Đang trình chiếu',
            ],
            $lifecycle['state'] === ShowtimeLifecycleService::COMPLETED
                && $lifecycle['now']->lt($lifecycle['room_ready_at']) => [
                    'key' => 'cleaning', 'label' => 'Đang vệ sinh',
                ],
            default => ['key' => 'ready', 'label' => 'Sẵn sàng'],
        };

        return view('admin.showtimes.show', compact(
            'showtime',
            'lifecycle',
            'roomState',
            'bookingCount',
            'recentBookings',
            'hasBookingHistory',
            'canEdit',
            'canCancel',
        ));
    }

    public function create()
    {
        return view('admin.showtimes.create', $this->formData());
    }

    public function store(StoreShowtimeRequest $request)
    {
        $room = Room::query()->findOrFail($request->validated('room_id'));
        $this->cinemaAccess->authorizeCinema($request->user(), (int) $room->cinema_id);
        try {
            $this->schedule->schedule(
                $request->validated(),
                function (Showtime $showtime): void {
                    $this->activityLogger->log(
                        'showtime.created',
                        $showtime,
                        after: $this->auditData($showtime),
                    );
                },
            );
        } catch (ShowtimeScheduleException|PricingConfigurationException $exception) {
            $field = $exception instanceof ShowtimeScheduleException ? $exception->field : 'room_id';

            return back()->withErrors([$field => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được tạo theo bảng giá hiện hành.');
    }

    public function edit(Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);
        $showtime->loadMissing(['movie', 'roomLayout', 'cinema', 'presentationFormat']);
        $this->assertUpcomingForMutation($showtime);

        return view('admin.showtimes.edit', [
            ...$this->formData(),
            'showtime' => $showtime,
            'showtimeWindow' => $this->schedule->windowFor($showtime),
            'hasBookingHistory' => $this->schedule->hasBookingHistory($showtime),
        ]);
    }

    public function update(UpdateShowtimeRequest $request, Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);
        $this->assertUpcomingForMutation($showtime);
        $targetRoom = Room::query()->findOrFail($request->validated('room_id'));
        $this->cinemaAccess->authorizeCinema($request->user(), (int) $targetRoom->cinema_id);

        try {
            $this->schedule->reschedule(
                $showtime,
                $request->validated(),
                function (Showtime $updated, Showtime $before): void {
                    $this->activityLogger->log(
                        'showtime.updated',
                        $updated,
                        $this->auditData($before),
                        $this->auditData($updated),
                    );
                },
            );
        } catch (ShowtimeScheduleException|PricingConfigurationException $exception) {
            $field = $exception instanceof ShowtimeScheduleException ? $exception->field : 'room_id';

            return back()->withErrors([$field => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được cập nhật.');
    }

    public function destroy(Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);
        DB::transaction(function () use ($showtime): void {
            $locked = Showtime::query()->whereKey($showtime->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') {
                return;
            }
            if ($locked->status !== 'active') {
                throw ValidationException::withMessages([
                    'showtime' => 'Chỉ suất chiếu đang hoạt động mới có thể hủy.',
                ]);
            }
            $locked->loadMissing(['movie', 'room.cinema']);
            if ($this->lifecycle->state($locked) === ShowtimeLifecycleService::COMPLETED) {
                throw ValidationException::withMessages([
                    'showtime' => 'Suất chiếu đã kết thúc nên không thể hủy.',
                ]);
            }
            if ($locked->bookings()->exists()) {
                throw ValidationException::withMessages([
                    'showtime' => 'Suất chiếu đã có lịch sử đặt vé nên không thể hủy trực tiếp.',
                ]);
            }

            $before = $this->auditData($locked);
            $locked->forceFill(['status' => 'cancelled'])->save();
            $this->activityLogger->log('showtime.cancelled', $locked, $before, ['status' => 'cancelled']);
        });

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được hủy và giữ lại trong lịch sử.');
    }

    private function formData(): array
    {
        $cinema = $this->cinemaAccess->currentCinema(auth()->user());

        return [
            'movies' => Movie::query()
                ->whereIn('status', Movie::SCHEDULABLE_STATUSES)
                ->with(['supportedPresentationFormats' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id')])
                ->orderBy('title')->get(),
            'presentationFormats' => PresentationFormat::query()->active()->orderBy('sort_order')->orderBy('id')->get(),
            'rooms' => $this->operationalRooms(),
            'cinema' => $cinema,
            'cleaningBufferMinutes' => $this->schedule->cleaningBufferMinutes(),
            'cinemaTimezone' => $cinema?->timezone ?? $this->schedule->timezone(),
        ];
    }

    private function operationalRooms()
    {
        $query = Room::query()->operational()->whereHas('latestPublishedLayout')
            ->with([
                'latestPublishedLayout',
                'cinema',
                'presentationCapabilities' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id'),
            ]);
        $this->cinemaAccess->scope($query, auth()->user(), 'rooms.cinema_id');

        return $query
            ->orderBy('code')->get();
    }

    private function assertOperationalShowtime(Showtime $showtime): void
    {
        $showtime->loadMissing('room');
        $this->cinemaAccess->authorizeCinema(auth()->user(), (int) $showtime->cinema_id);
        abort_unless($showtime->room?->cinema_id === $showtime->cinema_id && $showtime->room?->status === 'active', 404);
    }

    private function assertViewableShowtime(Showtime $showtime): void
    {
        $showtime->loadMissing('room');
        $this->cinemaAccess->authorizeCinema(auth()->user(), (int) $showtime->cinema_id);
        abort_unless((int) $showtime->room?->cinema_id === (int) $showtime->cinema_id, 404);
    }

    private function assertUpcomingForMutation(Showtime $showtime): void
    {
        abort_unless(
            $showtime->status === 'active'
                && $this->lifecycle->state($showtime) === ShowtimeLifecycleService::UPCOMING,
            409,
            'Chỉ có thể chỉnh sửa suất chiếu sắp diễn ra.',
        );
    }

    /** @return array<string, mixed> */
    private function auditData(Showtime $showtime): array
    {
        $showtime->loadMissing('ticketPrices.seatType');
        $window = $this->schedule->windowFor($showtime);

        return [
            'showtime_id' => $showtime->id,
            'movie_id' => $showtime->movie_id,
            'room_id' => $showtime->room_id,
            'room_layout_id' => $showtime->room_layout_id,
            'presentation_format_id' => $showtime->presentation_format_id,
            'show_date' => $showtime->show_date?->format('Y-m-d'),
            'show_time' => (string) $showtime->show_time,
            'movie_end_at' => $window->movieEnd->toIso8601String(),
            'room_available_at' => $window->operationalEnd->toIso8601String(),
            'cleaning_buffer' => $window->cleaningBufferMinutes,
            'ticket_prices' => $showtime->ticketPrices->map(fn ($price): array => [
                'seat_type_id' => (int) $price->seat_type_id,
                'seat_type' => (string) $price->seatType?->code,
                'final_unit_amount_vnd' => (int) $price->final_unit_amount_vnd,
                'price_book_version_id' => (int) $price->price_book_version_id,
                'pricing_fingerprint' => (string) $price->pricing_fingerprint,
            ])->values()->all(),
            'status' => $showtime->status,
        ];
    }
}
