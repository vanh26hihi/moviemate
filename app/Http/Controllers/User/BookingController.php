<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\FoodItem;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\BookingCheckoutDraftService;
use App\Services\BookingCheckoutPreviewService;
use App\Services\CinemaContext;
use App\Services\GuestBookingAccessService;
use App\Services\RoomLayoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly RoomLayoutService $layouts,
        private readonly GuestBookingAccessService $guestAccess,
        private readonly BookingCheckoutDraftService $drafts,
        private readonly BookingCheckoutPreviewService $previews,
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

        $draft = $this->drafts->start($request, $showtime->id, $seatIds);
        $preview = $this->previews->preview($draft);
        $foods = FoodItem::query()->where('active', true)->orderBy('name')->get();

        return view('user.bookings.food', compact('draft', 'preview', 'foods'));
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
            'foodOrder.items',
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
            'foodOrder.items',
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
