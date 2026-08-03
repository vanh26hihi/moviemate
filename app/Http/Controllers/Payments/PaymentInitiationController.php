<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\GuestBookingAccessService;
use App\Services\Payments\PaymentInitiationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PaymentInitiationController extends Controller
{
    public function __invoke(
        Request $request,
        Booking $booking,
        GuestBookingAccessService $guestAccess,
        PaymentInitiationService $payments,
    ): RedirectResponse {
        $this->authorizeBooking($request, $booking, $guestAccess);
        $result = $payments->initiate($booking);

        abort_unless(is_string($result->orderUrl) && $result->orderUrl !== '', 409);

        return redirect()->away($result->orderUrl);
    }

    private function authorizeBooking(
        Request $request,
        Booking $booking,
        GuestBookingAccessService $guestAccess,
    ): void {
        if (Auth::check()) {
            Gate::authorize('view', $booking);

            return;
        }

        abort_unless($guestAccess->allows($request, $booking), 404);
    }
}
