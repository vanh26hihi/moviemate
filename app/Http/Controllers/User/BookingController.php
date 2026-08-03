<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Mail\BookingTicketMail;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\CinemaContext;
use App\Services\RoomLayoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly RoomLayoutService $layouts
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

        $bookedSeatIds = BookingSeat::whereHas('booking', function ($query) use ($showtime) {
            $query->where('showtime_id', $showtime->id)
                ->whereNotIn('booking_status', ['cancelled', 'expired']);
        })->pluck('seat_id')->toArray();

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

        $bookedSeatIds = BookingSeat::whereHas('booking', function ($query) use ($showtime) {
            $query->where('showtime_id', $showtime->id)
                ->whereNotIn('booking_status', ['cancelled', 'expired']);
        })->whereIn('seat_id', $seatIds)->pluck('seat_id')->toArray();

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
        ]);
    }

    /**
     * Store a booking with fake successful payment.
     *
     * @throws \Throwable
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'showtime_id' => ['required', 'integer', 'exists:showtimes,id'],
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => ['integer', 'distinct'],
            'payment_method' => ['required', 'in:fake,counter,vnpay'],
            'customer_email' => ['required', 'email:rfc', 'max:255'],
        ], [
            'seat_ids.required' => 'Vui lòng chọn ít nhất một ghế.',
            'seat_ids.array' => 'Dữ liệu ghế không hợp lệ.',
            'seat_ids.*.distinct' => 'Danh sách ghế bị trùng.',
            'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
        ]);

        $booking = DB::transaction(function () use ($validated) {
            $showtime = Showtime::with(['movie', 'cinema', 'room', 'roomLayout'])
                ->lockForUpdate()
                ->findOrFail($validated['showtime_id']);

            if (! $this->isShowtimeAvailable($showtime)) {
                throw ValidationException::withMessages([
                    'showtime' => 'Suất chiếu này đã qua giờ hoặc không còn khả dụng.',
                ]);
            }

            $seatIds = collect($validated['seat_ids'])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $layout = $this->layouts->resolveForShowtime($showtime);
            $seats = Seat::where('room_id', $showtime->room_id)
                ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id))
                ->whereIn('id', $seatIds)
                ->lockForUpdate()
                ->orderBy('row')
                ->orderBy('number')
                ->get();

            if ($seats->count() !== count($seatIds)) {
                throw ValidationException::withMessages([
                    'seat_ids' => 'Ghế đã chọn không hợp lệ hoặc không thuộc phòng chiếu này.',
                ]);
            }

            $maintenanceSeat = $seats->first(fn ($seat) => $seat->status !== 'active');
            if ($maintenanceSeat) {
                throw ValidationException::withMessages([
                    'seat_ids' => 'Có ghế đang bảo trì, vui lòng chọn ghế khác.',
                ]);
            }

            if (! $this->coupleSelectionIsComplete($seats, $layout->id)) {
                throw ValidationException::withMessages([
                    'seat_ids' => 'Ghế đôi phải được gửi đủ cả cặp thuộc cùng layout.',
                ]);
            }

            $alreadyBookedSeatIds = BookingSeat::whereHas('booking', function ($query) use ($showtime) {
                $query->where('showtime_id', $showtime->id)
                    ->whereNotIn('booking_status', ['cancelled', 'expired']);
            })
                ->whereIn('seat_id', $seatIds)
                ->lockForUpdate()
                ->pluck('seat_id')
                ->all();

            if (! empty($alreadyBookedSeatIds)) {
                throw ValidationException::withMessages([
                    'seat_ids' => 'Một hoặc nhiều ghế đã bị người khác đặt trước. Vui lòng chọn lại.',
                ]);
            }

            $seatPrices = [];
            $totalAmount = 0;

            foreach ($seats as $seat) {
                $price = $showtime->priceForSeatType($seat->type);

                $seatPrices[$seat->id] = $price;
                $totalAmount += $price;
            }

            $booking = Booking::create([
                'user_id' => Auth::id(),
                'customer_email' => $validated['customer_email'],
                'showtime_id' => $showtime->id,
                'booking_code' => $this->generateBookingCode(),
                'total_amount' => $totalAmount,
                'payment_status' => 'paid',
                'booking_status' => 'paid',
            ]);

            foreach ($seats as $seat) {
                BookingSeat::create([
                    'booking_id' => $booking->id,
                    'seat_id' => $seat->id,
                    'price' => $seatPrices[$seat->id],
                ]);
            }

            $booking->payment()->create([
                'payment_method' => $validated['payment_method'],
                'amount' => $totalAmount,
                'status' => 'success',
                'transaction_code' => 'FAKE-'.now()->format('YmdHis').'-'.$booking->id,
                'paid_at' => now(),
            ]);

            return $booking;
        });

        try {
            Mail::to($booking->recipient_email)->send(new BookingTicketMail($booking));
        } catch (\Throwable $exception) {
            Log::warning('Không thể gửi email vé MovieMate.', [
                'booking_id' => $booking->id,
                'email' => $booking->recipient_email,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('user.bookings.success', $booking)
                ->with('warning', 'Đặt vé thành công nhưng hệ thống chưa gửi được email. Bạn vẫn có thể xem vé QR tại đây.');
        }

        return redirect()->route('user.bookings.success', $booking);
    }

    /**
     * Show booking success page.
     */
    public function success(Booking $booking)
    {
        $this->authorizeBookingView($booking);

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

    public function ticket(Booking $booking)
    {
        $this->authorizeBookingView($booking);

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

    private function authorizeBookingView(Booking $booking): void
    {
        if (Auth::check()) {
            Gate::authorize('view', $booking);

            return;
        }

        abort_unless($booking->user_id === null, 403);
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

    /**
     * Generate unique booking code with format MMT-YYYY-XXXX.
     */
    protected function generateBookingCode(): string
    {
        $year = now()->format('Y');

        do {
            $latestBooking = Booking::whereYear('created_at', $year)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $nextNumber = $latestBooking
                ? ((int) substr($latestBooking->booking_code, -4)) + 1
                : 1;

            $bookingCode = 'MMT-'.$year.'-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        } while (Booking::where('booking_code', $bookingCode)->exists());

        return $bookingCode;
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
