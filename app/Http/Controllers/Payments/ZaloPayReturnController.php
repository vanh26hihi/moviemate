<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\ZaloPayConfig;
use App\Domain\Payments\ZaloPaySigner;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\GuestBookingAccessService;
use App\Services\Payments\PaymentReturnTokenService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ZaloPayReturnController extends Controller
{
    public function __invoke(
        Request $request,
        ZaloPayConfig $config,
        ZaloPaySigner $signer,
        PaymentReturnTokenService $returnTokens,
        GuestBookingAccessService $guestAccess,
    ): Response|View {
        $appTransId = $request->query('apptransid');
        abort_unless(is_string($appTransId), 404);

        $payment = Payment::query()
            ->with('booking')
            ->where('provider', 'zalopay')
            ->where('app_trans_id', $appTransId)
            ->firstOrFail();

        if (Auth::check()) {
            Gate::authorize('view', $payment->booking);
        } else {
            $returnToken = $request->query('return_token');
            if (is_string($returnToken)) {
                abort_unless($returnTokens->verify($payment, $returnToken), 404);
                abort_unless($guestAccess->grantPaymentReturn($request, $payment->booking), 404);
            } else {
                abort_unless($guestAccess->allows($request, $payment->booking), 404);
            }
        }

        if ($request->query->count() > 1) {
            $checksum = $request->query('checksum');
            $integrityVerified = is_string($checksum)
                && $signer->verifyReturn($request->query(), $checksum, $config->key2);
            $request->session()->flash('payment_return_integrity.'.$payment->id, $integrityVerified);

            return redirect()->route('payments.zalopay.return', [
                'apptransid' => $payment->app_trans_id,
            ]);
        }

        $integrityVerified = (bool) $request->session()->pull(
            'payment_return_integrity.'.$payment->id,
            false,
        );

        $payment->refresh();
        $payment->load('booking');

        return view('payments.return', [
            'payment' => $payment,
            'booking' => $payment->booking,
            'integrityVerified' => $integrityVerified,
        ]);
    }
}
