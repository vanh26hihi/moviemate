<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\ZaloPayConfig;
use App\Domain\Payments\ZaloPaySigner;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentReturnTokenService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ZaloPayReturnController extends Controller
{
    public function __invoke(
        Request $request,
        ZaloPayConfig $config,
        ZaloPaySigner $signer,
        PaymentReturnTokenService $returnTokens,
    ): View {
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
            abort_unless($returnTokens->verify($payment, $request->query('return_token')), 404);
        }

        $checksum = $request->query('checksum');
        $integrityVerified = is_string($checksum)
            && $signer->verifyReturn($request->query(), $checksum, $config->key2);

        $payment->refresh();
        $payment->load('booking');

        return view('payments.return', [
            'payment' => $payment,
            'booking' => $payment->booking,
            'integrityVerified' => $integrityVerified,
        ]);
    }
}
