<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\Admin\PaymentReconciliationQuery;
use App\Services\Payments\PaymentReviewResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use LogicException;

class PaymentReviewController extends Controller
{
    public function index(): View
    {
        $payments = Payment::query()
            ->with('booking')
            ->where('status', Payment::STATUS_REVIEW)
            ->latest('id')
            ->paginate(25);

        return view('admin.payment-reviews.index', compact('payments'));
    }

    public function resolve(
        Request $request,
        string $paymentId,
        PaymentReviewResolutionService $reviews,
        PaymentReconciliationQuery $queue,
        ActivityLogger $activities,
    ): RedirectResponse {
        $payment = Payment::query()->findOrFail($paymentId);

        if ($payment->status !== Payment::STATUS_REVIEW) {
            return redirect()->route('admin.payment-reviews.index')
                ->with('error', 'Chỉ giao dịch đang chờ kiểm tra mới có thể được đối soát thủ công.');
        }

        $rateKey = implode(':', ['admin-payment', 'review', $request->user()->id, $payment->id]);
        abort_if(RateLimiter::tooManyAttempts($rateKey, 6), 429);
        RateLimiter::hit($rateKey, 60);

        $before = $payment->status;

        try {
            $result = $reviews->resolve($payment, $request->user());
        } catch (LogicException) {
            return redirect()->route('admin.payment-reviews.index')
                ->with('error', 'Không thể hoàn tất đối soát giao dịch hiện tại.');
        }

        $payment->refresh();
        $activities->log('payment.reconciliation_completed', $payment, [
            'payment_status' => $before,
        ], [
            'payment_status' => $payment->status,
        ], [
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'provider' => $payment->provider,
            'result' => $result->category,
        ]);
        if ($payment->status === Payment::STATUS_SUCCESS && $payment->verified_at !== null) {
            $activities->log('payment.review_resolved', $payment, [
                'payment_status' => $before,
            ], [
                'payment_status' => $payment->status,
            ], [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'provider' => $payment->provider,
                'result' => 'verified_provider_success',
            ]);
        }
        $queue->forgetBadge();

        return redirect()->route('admin.payment-reviews.index')
            ->with('success', $result->message);
    }
}
