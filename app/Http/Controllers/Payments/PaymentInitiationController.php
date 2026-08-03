<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingTokenService;
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
        BookingTokenService $bookingTokens,
        PaymentInitiationService $payments,
    ): RedirectResponse {
        $this->authorizeBooking($request, $booking, $bookingTokens);
        $result = $payments->initiate($booking);

        abort_unless(is_string($result->orderUrl) && $result->orderUrl !== '', 409);

        return redirect()->away($result->orderUrl);
    }

    private function authorizeBooking(
        Request $request,
        Booking $booking,
        BookingTokenService $tokens,
    ): void {
        if (Auth::check()) {
            Gate::authorize('view', $booking);

            return;
        }

        $guestToken = $request->input('guest_token');
        abort_unless(
            $booking->user_id === null
                && $tokens->verifyHash(
                    $booking->guest_access_token_hash,
                    is_string($guestToken) ? $guestToken : null,
                ),
            404,
        );
    }
}
