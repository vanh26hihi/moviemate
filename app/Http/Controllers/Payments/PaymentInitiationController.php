<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentInitiationException;
use App\Exceptions\VnpayResponseException;
use App\Exceptions\VnpayTransportException;
use App\Exceptions\ZaloPayResponseException;
use App\Exceptions\ZaloPayTransportException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
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
        try {
            $result = $payments->initiate(
                $booking,
                (string) $request->route('payment_provider', config('payment.driver')),
                $request->ip(),
            );
        } catch (PaymentInitiationException|ZaloPayResponseException|ZaloPayTransportException|VnpayResponseException|VnpayTransportException) {
            $paymentStatus = $booking->payments()->latest('id')->value('status');
            $statusRoute = match ($paymentStatus) {
                Payment::STATUS_SUCCESS => 'user.bookings.success',
                Payment::STATUS_FAILED => 'user.bookings.failed',
                Payment::STATUS_REVIEW => 'user.bookings.payment-review',
                Payment::STATUS_EXPIRED => 'user.bookings.expired',
                Payment::STATUS_UNRESOLVED => 'user.bookings.pending',
                default => 'user.bookings.pending',
            };

            return redirect()
                ->route($statusRoute, $booking)
                ->with('warning', 'MovieMate đang đối soát lần thanh toán ZaloPay hiện tại.');
        }

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
