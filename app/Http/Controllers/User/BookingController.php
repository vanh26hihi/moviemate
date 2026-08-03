<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use App\Services\CinemaContext;
use App\Services\GuestBookingAccessService;
use App\Services\RoomLayoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly RoomLayoutService $layouts,
        private readonly BookingCheckoutService $checkoutService,
        private readonly BookingTokenService $tokens,
        private readonly GuestBookingAccessService $guestAccess,
    ) {}

    /**
     * Show seat selection page for a given showtime.
     */
    public function selectSeat(Showtime $showtime)
    {
        $showtime->load(['movie', 'cinema', 'room', 'roomLayout.cells.seat']);

        if (! $this->isShowtimeAvailable($showtime)) {
            return redirect()
                ->route('user.movies.show', $showtime->movie->slug)
                ->with('error', 'Suất chiếu này đã qua giờ hoặc không còn khả dụng.');
        }

        $layout = $this->layouts->resolveForShowtime($showtime);
        $layoutCells = $layout->cells->sortBy(fn ($cell) => sprintf('%03d:%03d', $cell->y_position, $cell->x_position))->values();
        $seats = $layoutCells->where('cell_type', 'seat')->pluck('seat')->filter()->values();

        $bookedSeatIds = BookingSeat::query()
            ->where('showtime_id', $showtime->id)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->pluck('seat_id')
            ->all();

        return view('user.bookings.select-seat', compact(
            'showtime',
            'layout',
            'layoutCells',
            'seats',
            'bookedSeatIds'
        ));
    }

    /**
     * Show checkout page for selected seats.
     */
    public function checkout(Request $request, Showtime $showtime)
    {
        $showtime->load(['movie', 'cinema', 'room', 'roomLayout']);

        if (! $this->isShowtimeAvailable($showtime)) {
            return redirect()
                ->route('user.movies.show', $showtime->movie->slug)
                ->with('error', 'Suất chiếu này đã qua giờ hoặc không còn khả dụng.');
        }

        $seatIds = $this->parseSeatIds($request->query('selected_seats', ''));

        if (empty($seatIds)) {
            return redirect()
                ->route('user.bookings.selectSeat', $showtime->id)
                ->with('error', 'Vui lòng chọn ít nhất một ghế.');
        }

        $layout = $this->layouts->resolveForShowtime($showtime);
        $seats = Seat::where('room_id', $showtime->room_id)
            ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id))
            ->whereIn('id', $seatIds)
            ->orderBy('row')
            ->orderBy('number')
            ->get();

        if ($seats->count() !== count($seatIds)) {
            return redirect()
                ->route('user.bookings.selectSeat', $showtime->id)
                ->with('error', 'Danh sách ghế không hợp lệ.');
        }

        if ($seats->contains(fn ($seat) => $seat->status !== 'active')) {
            return redirect()
                ->route('user.bookings.selectSeat', $showtime->id)
                ->with('error', 'Có ghế đang bảo trì hoặc không khả dụng.');
        }

        if (! $this->coupleSelectionIsComplete($seats, $layout->id)) {
            return redirect()->route('user.bookings.selectSeat', $showtime->id)
                ->with('error', 'Ghế đôi phải được chọn đủ cả cặp.');
        }

        $bookedSeatIds = BookingSeat::query()
            ->where('showtime_id', $showtime->id)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->whereIn('seat_id', $seatIds)
            ->pluck('seat_id')
            ->all();

        if (! empty($bookedSeatIds)) {
            return redirect()
                ->route('user.bookings.selectSeat', $showtime->id)
                ->with('error', 'Một số ghế bạn chọn đã được người khác đặt trước.');
        }

        $seatSummaries = $seats->map(function ($seat) use ($showtime) {
            $price = $showtime->priceForSeatType($seat->type);

            return [
                'id' => $seat->id,
                'seat_code' => $seat->seat_code,
                'type' => $seat->type,
                'price' => (float) $price,
            ];
        });

        $totalAmount = $seatSummaries->sum('price');

        return view('user.bookings.checkout', [
            'showtime' => $showtime,
            'seats' => $seats,
            'seatSummaries' => $seatSummaries,
            'totalAmount' => $totalAmount,
            'user' => Auth::user(),
            'checkoutToken' => $this->tokens->issueCheckoutToken(),
        ]);
    }

    /**
     * Store a pending booking reservation. Payment confirmation is out of scope.
     *
     * @throws \Throwable
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'showtime_id' => ['required', 'integer', 'exists:showtimes,id'],
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => ['integer', 'distinct'],
            'customer_email' => ['required', 'email:rfc', 'max:255'],
            'checkout_token' => ['required', 'string', 'max:200'],
        ], [
            'seat_ids.required' => 'Vui lòng chọn ít nhất một ghế.',
            'seat_ids.array' => 'Dữ liệu ghế không hợp lệ.',
            'seat_ids.*.distinct' => 'Danh sách ghế bị trùng.',
            'checkout_token.required' => 'Phiên xác nhận đặt ghế không hợp lệ.',
        ]);

        if (! $this->tokens->isValidCheckoutToken($validated['checkout_token'])) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Phiên xác nhận đặt ghế không hợp lệ.',
            ]);
        }

        $result = $this->checkoutService->createPendingBooking(
            (int) $validated['showtime_id'],
            $validated['seat_ids'],
            Auth::id(),
            $validated['customer_email'],
            $validated['checkout_token'],
        );

        if ($result->guestAccessToken !== null) {
            return response()->view('user.bookings.guest-handoff', [
                'accessUrl' => route('user.bookings.access.show', $result->booking),
                'guestAccessToken' => $result->guestAccessToken,
                'destination' => 'success',
            ]);
        }

        return redirect()->route('user.bookings.success', $result->booking);
    }

    /**
     * Show booking success page.
     */
    public function success(Request $request, Booking $booking)
    {
        $this->authorizeBookingView($request, $booking);

        $booking->load([
            'user',
            'payment',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room',
            'bookingSeats.seat',
        ]);

        return view('user.bookings.success', compact('booking'));
    }

    public function ticket(Request $request, Booking $booking)
    {
        $this->authorizeBookingView($request, $booking);

        $booking->load([
            'user',
            'payment',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room',
            'bookingSeats.seat',
        ]);

        return view('user.bookings.ticket', compact('booking'));
    }

    private function authorizeBookingView(Request $request, Booking $booking): void
    {
        if (Auth::check()) {
            Gate::authorize('view', $booking);

            return;
        }

        abort_unless(
            $this->guestAccess->allows($request, $booking),
            404,
        );
    }

    /**
     * Parse seat ids from comma-separated query string.
     */
    protected function parseSeatIds(string $selectedSeats): array
    {
        return collect(explode(',', $selectedSeats))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => $id !== '' && ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Check whether a showtime can still be booked.
     */
    protected function isShowtimeAvailable(Showtime $showtime): bool
    {
        if ($showtime->status !== 'active'
            || $showtime->cinema_id !== $this->cinemaContext->id()
            || $showtime->room?->status !== 'active'
            || $showtime->room?->cinema_id !== $this->cinemaContext->id()
            || ! $showtime->roomLayout
            || $showtime->roomLayout->status !== 'published'
            || $showtime->roomLayout->room_id !== $showtime->room_id) {
            return false;
        }

        $showDateTime = Carbon::parse(
            $showtime->show_date->format('Y-m-d').' '.$showtime->show_time
        );

        return $showDateTime->isFuture();
    }

    private function coupleSelectionIsComplete($seats, int $layoutId): bool
    {
        $couples = $seats->where('type', 'couple')->groupBy('pair_code');
        foreach ($couples as $pairCode => $selectedPair) {
            if (! $pairCode || $selectedPair->count() !== 2
                || $selectedPair->pluck('pair_position')->sort()->values()->all() !== ['left', 'right']) {
                return false;
            }

            $layoutPairCount = Seat::query()
                ->where('room_id', $selectedPair->first()->room_id)
                ->where('type', 'couple')
                ->where('pair_code', $pairCode)
                ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layoutId))
                ->count();
            if ($layoutPairCount !== 2) {
                return false;
            }
        }

        return true;
    }
}
