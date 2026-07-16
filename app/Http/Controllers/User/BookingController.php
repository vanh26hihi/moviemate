<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /**
     * Show seat selection page for a given showtime.
     */
    public function selectSeat(Showtime $showtime)
    {
        $showtime->load(['movie', 'cinema', 'room']);

        if (! $this->isShowtimeAvailable($showtime)) {
            return redirect()
                ->route('user.movies.show', $showtime->movie->slug)
                ->with('error', 'Suất chiếu này đã qua giờ hoặc không còn khả dụng.');
        }

        $seats = Seat::where('room_id', $showtime->room_id)
            ->orderBy('row')
            ->orderBy('number')
            ->get();

        $bookedSeatIds = BookingSeat::whereHas('booking', function ($query) use ($showtime) {
            $query->where('showtime_id', $showtime->id)
                ->whereNotIn('booking_status', ['cancelled', 'expired']);
        })->pluck('seat_id')->toArray();

        $seatsByRow = $seats->groupBy('row');

        return view('user.bookings.select-seat', compact(
            'showtime',
            'seats',
            'seatsByRow',
            'bookedSeatIds'
        ));
    }

    /**
     * Show checkout page for selected seats.
     */
    public function checkout(Request $request, Showtime $showtime)
    {
        $showtime->load(['movie', 'cinema', 'room']);

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

        $seats = Seat::where('room_id', $showtime->room_id)
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
            $price = $seat->type === 'vip'
                ? ($showtime->vip_price ?? $showtime->price)
                : $showtime->price;

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
        ], [
            'seat_ids.required' => 'Vui lòng chọn ít nhất một ghế.',
            'seat_ids.array' => 'Dữ liệu ghế không hợp lệ.',
            'seat_ids.*.distinct' => 'Danh sách ghế bị trùng.',
        ]);

        $booking = DB::transaction(function () use ($validated) {
            $showtime = Showtime::with(['movie', 'cinema', 'room'])
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

            $seats = Seat::where('room_id', $showtime->room_id)
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
                $price = $seat->type === 'vip'
                    ? (float) ($showtime->vip_price ?? $showtime->price)
                    : (float) $showtime->price;

                $seatPrices[$seat->id] = $price;
                $totalAmount += $price;
            }

            $booking = Booking::create([
                'user_id' => Auth::id(),
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
                'transaction_code' => 'FAKE-' . now()->format('YmdHis') . '-' . $booking->id,
                'paid_at' => now(),
            ]);

            return $booking;
        });

        return redirect()->route('user.bookings.success', $booking);
    }

    /**
     * Show booking success page.
     */
    public function success(Booking $booking)
    {
        abort_unless($booking->user_id === Auth::id(), 403);

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
        if ($showtime->status !== 'active') {
            return false;
        }

        $showDateTime = Carbon::parse(
            $showtime->show_date->format('Y-m-d') . ' ' . $showtime->show_time
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

            $bookingCode = 'MMT-' . $year . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        } while (Booking::where('booking_code', $bookingCode)->exists());

        return $bookingCode;
    }
}