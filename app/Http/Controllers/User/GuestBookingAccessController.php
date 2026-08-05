<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\GuestBookingAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestBookingAccessController extends Controller
{
    public function show(Booking $booking, GuestBookingAccessService $access): View
    {
        abort_unless($access->hasExchangeableCredential($booking), 404);

        return view('user.bookings.access', compact('booking'));
    }

    public function exchange(
        Request $request,
        Booking $booking,
        GuestBookingAccessService $access,
    ): JsonResponse {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:200'],
            'destination' => ['required', 'in:success,ticket'],
        ]);

        abort_unless($access->exchange($request, $booking, $validated['token']), 404);

        $destination = $validated['destination'] === 'ticket'
            ? 'user.bookings.ticket'
            : 'user.bookings.success';

        return response()->json([
            'redirect_url' => route($destination, $booking),
        ]);
    }
}
