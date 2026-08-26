<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentConfigurationException;
use App\Exceptions\PayOsResponseException;
use App\Exceptions\PayOsTransportException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\GuestBookingAccessService;
use App\Services\Payments\PaymentReturnTokenService;
use App\Services\Payments\PayOsPaymentReconciliationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class PayOsReturnController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentReturnTokenService $returnTokens,
        PayOsPaymentReconciliationService $reconciliation,
        GuestBookingAccessService $guestAccess,
    ): Response|View|RedirectResponse {
        $mode = $request->route('payos_return_mode') === 'cancel' ? 'cancel' : 'return';
        if ($request->query->has('orderCode')) {
            $orderCode = $request->query('orderCode');
            abort_unless(is_string($orderCode) && preg_match('/^[1-9][0-9]{0,15}$/D', $orderCode) === 1, 404);
            $payment = Payment::query()->with('booking')
                ->where('provider', 'payos')->where('order_code', $orderCode)->firstOrFail();
            $this->authorizePayment($request, $payment, $returnTokens, true);

            $verified = true;
            try {
                $reconciliation->reconcile($payment);
            } catch (PaymentConfigurationException|PayOsTransportException|PayOsResponseException) {
                $verified = false;
            }
            $request->session()->flash('payment_return_integrity.'.$payment->id, $verified);

            return redirect()->route("payments.payos.{$mode}", ['attempt' => $payment->id]);
        }

        $attempt = $request->query('attempt');
        abort_unless(is_string($attempt) && preg_match('/^[1-9][0-9]{0,18}$/D', $attempt) === 1, 404);
        $payment = Payment::query()->with('booking')
            ->where('provider', 'payos')->findOrFail((int) $attempt);
        $this->authorizePayment($request, $payment, $returnTokens, false);
        $integrityVerified = (bool) $request->session()->pull('payment_return_integrity.'.$payment->id, false);
        $payment->refresh()->load(['booking.showtimeCancellationImpact', 'booking.refundCase']);
        $canViewBooking = Auth::check() || $guestAccess->allows($request, $payment->booking);
        if ($this->usesStaffCounterResult($payment)) {
            return redirect()->route('staff.counter.payment-result', [
                'booking' => $payment->booking,
                'returned' => 1,
            ]);
        }

        return view('payments.return', [
            'payment' => $payment,
            'booking' => $payment->booking,
            'integrityVerified' => $integrityVerified,
            'canViewTicket' => $canViewBooking,
            'canViewBooking' => $canViewBooking,
            'payOsCancelReturn' => $mode === 'cancel',
        ]);
    }

    private function usesStaffCounterResult(Payment $payment): bool
    {
        return $payment->booking?->sales_channel === Booking::SALES_CHANNEL_COUNTER
            && Auth::user()?->hasPermission('counter_sales.view') === true;
    }

    private function authorizePayment(
        Request $request,
        Payment $payment,
        PaymentReturnTokenService $tokens,
        bool $exchange,
    ): void {
        if (Auth::check()) {
            Gate::authorize('view', $payment->booking);

            return;
        }
        abort_unless($exchange
            ? $tokens->exchange($request, $payment, $request->query('state'))
            : $tokens->allows($request, $payment), 404);
    }
}
