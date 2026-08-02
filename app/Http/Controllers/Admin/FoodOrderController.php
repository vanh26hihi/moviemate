<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Cinema;
use Illuminate\Http\Request;

class FoodOrderController extends Controller
{
    public function index(Request $request)
    {
        $bookings = Booking::query()
            ->with(['user', 'showtime.movie', 'showtime.cinema', 'showtime.room', 'bookingSeats.seat', 'payment'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim((string) $request->search);
                $query->where(function ($query) use ($term) {
                    $query->where('booking_code', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('date'), fn ($query) => $query->whereDate('created_at', $request->date))
            ->when($request->filled('cinema_id'), fn ($query) => $query->whereHas('showtime', fn ($showtime) => $showtime->where('cinema_id', $request->cinema_id)))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->payment_status))
            ->when($request->filled('ticket_status'), function ($query) use ($request) {
                $request->ticket_status === 'used'
                    ? $query->where('booking_status', 'used')
                    : $query->where('payment_status', 'paid')->where('booking_status', '!=', 'used');
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $cinemas = Cinema::orderBy('name')->get(['id', 'name']);

        return view('admin.bookings.index', compact('bookings', 'cinemas'));
    }

    public function show(Booking $booking)
    {
        $booking->load(['user', 'showtime.movie', 'showtime.cinema', 'showtime.room', 'bookingSeats.seat', 'payment']);
        $bookingCount = Booking::where('user_id', $booking->user_id)->count();

        return view('admin.bookings.show', compact('booking', 'bookingCount'));
    }
}
