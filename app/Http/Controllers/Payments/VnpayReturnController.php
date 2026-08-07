<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\VnpayConfig;
use App\Domain\Payments\VnpaySigner;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\GuestBookingAccessService;
use App\Services\Payments\PaymentReturnTokenService;
use App\Services\Payments\VnpayExplicitCancellationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class VnpayReturnController extends Controller
{
    public function __invoke(
        Request $request,
        VnpayConfig $config,
        VnpaySigner $signer,
        PaymentReturnTokenService $returnTokens,
        GuestBookingAccessService $guestAccess,
        VnpayExplicitCancellationService $cancellations,
    ): Response|View {
        if ($request->query->has('vnp_TxnRef')) {
            try {
                $parameters = $signer->parseQueryString((string) $request->server('QUERY_STRING', ''));
            } catch (InvalidArgumentException) {
                abort(404);
            }
            $checksum = $parameters['vnp_SecureHash'] ?? null;
            abort_unless(is_string($checksum)
                && ($parameters['vnp_TmnCode'] ?? null) === $config->tmnCode
                && $signer->verifyPayment($parameters, $checksum, $config->hashSecret), 404);

            $reference = $parameters['vnp_TxnRef'] ?? null;
            abort_unless(is_string($reference), 404);
            $payment = Payment::query()->with('booking')
                ->where('provider', 'vnpay')->where('order_code', $reference)->firstOrFail();
            $this->authorizePayment($request, $payment, $returnTokens, true);
            $cancelRequested = ($parameters['vnp_ResponseCode'] ?? null) === '24';
            if ($cancelRequested) {
                abort_unless($this->amountMatches($payment, $parameters['vnp_Amount'] ?? null), 404);
                $cancellations->finalizeVerified($payment, $parameters, 'return');
                $request->session()->flash('payment_return_cancel_requested.'.$payment->id, true);
            }
            $request->session()->flash('payment_return_integrity.'.$payment->id, true);

            return redirect()->route('payments.vnpay.return', ['ref' => $payment->order_code]);
        }

        $reference = $request->query('ref');
        abort_unless(is_string($reference) && preg_match('/^[A-Za-z0-9]{1,100}$/D', $reference) === 1, 404);
        $payment = Payment::query()->with('booking')
            ->where('provider', 'vnpay')->where('order_code', $reference)->firstOrFail();
        $this->authorizePayment($request, $payment, $returnTokens, false);
        $integrityVerified = (bool) $request->session()->pull('payment_return_integrity.'.$payment->id, false);
        $cancelRequested = (bool) $request->session()->pull('payment_return_cancel_requested.'.$payment->id, false);
        $payment->refresh()->load('booking');
        $canViewBooking = Auth::check() || $guestAccess->allows($request, $payment->booking);

        return view('payments.return', [
            'payment' => $payment,
            'booking' => $payment->booking,
            'integrityVerified' => $integrityVerified,
            'canViewTicket' => $canViewBooking,
            'canViewBooking' => $canViewBooking,
            'cancelRequested' => $cancelRequested,
        ]);
    }

    private function amountMatches(Payment $payment, mixed $providerAmount): bool
    {
        if (! is_string($providerAmount)
            || preg_match('/^[0-9]{3,15}$/D', $providerAmount) !== 1
            || $payment->amount <= 0
            || $payment->amount > intdiv(PHP_INT_MAX, 100)) {
            return false;
        }

        return hash_equals((string) ($payment->amount * 100), $providerAmount);
    }

    private function authorizePayment(Request $request, Payment $payment, PaymentReturnTokenService $tokens, bool $exchange): void
    {
        if (Auth::check()) {
            Gate::authorize('view', $payment->booking);

            return;
        }

        abort_unless($exchange
            ? $tokens->exchange($request, $payment, $request->query('state'))
            : $tokens->allows($request, $payment), 404);
    }
}
