<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PaymentConfigurationException;
use App\Exceptions\PaymentInitiationException;
use App\Exceptions\PayOsResponseException;
use App\Exceptions\PayOsTransportException;
use App\Exceptions\VnpayResponseException;
use App\Exceptions\VnpayTransportException;
use App\Exceptions\ZaloPayResponseException;
use App\Exceptions\ZaloPayTransportException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\Admin\PaymentReconciliationQuery;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Payments\PaymentReviewResolutionService;
use App\Support\StatusLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use LogicException;

final class PaymentReconciliationController extends Controller
{
    public function index(Request $request, PaymentReconciliationQuery $payments): View
    {
        $perPage = in_array((int) $request->query('per_page', 25), [15, 25, 50], true)
            ? (int) $request->query('per_page', 25) : 25;

        return view('admin.payment-reconciliation.index', [
            'payments' => $payments->paginate($perPage),
            'perPage' => $perPage,
        ]);
    }

    public function queryProvider(
        Request $request,
        Payment $payment,
        PaymentReconciliationService $reconciliation,
        PaymentReconciliationQuery $queue,
        ActivityLogger $activities,
    ): RedirectResponse {
        if (! in_array($payment->status, Payment::RECONCILABLE_STATUSES, true)) {
            return back()->with('warning', 'Giao dịch này không ở trạng thái được phép truy vấn provider. Không có dữ liệu nào bị thay đổi.');
        }
        if (! in_array($payment->provider, Payment::SUPPORTED_PROVIDERS, true)) {
            return back()->with('error', 'Nhà cung cấp này chưa hỗ trợ truy vấn giao dịch. Dữ liệu thanh toán được giữ nguyên.');
        }

        $this->assertRateLimit('query-provider', $request, $payment, 6);
        $before = $payment->status;
        $activities->log('payment.provider_query_requested', $payment, context: [
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'provider' => $payment->provider,
        ]);

        try {
            $result = $reconciliation->reconcile($payment);
        } catch (PaymentConfigurationException|PaymentInitiationException|PayOsResponseException|PayOsTransportException|VnpayResponseException|VnpayTransportException|ZaloPayResponseException|ZaloPayTransportException $exception) {
            report($exception);

            return back()->with('error', 'Chưa nhận được kết quả provider đủ tin cậy. Giao dịch không bị ép sang thành công.');
        }

        $payment->refresh();
        $activities->log('payment.provider_query_completed', $payment, [
            'payment_status' => $before,
        ], [
            'payment_status' => $payment->status,
        ], [
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'provider' => $payment->provider,
            'result' => $result,
        ]);
        $this->logReviewTransition($activities, $payment, $before);
        $queue->forgetBadge();

        return back()->with('success', 'Đã truy vấn provider. Trạng thái hiện tại: '.StatusLabel::for('payment', $payment->status).'.');
    }

    public function reconcile(
        Request $request,
        Payment $payment,
        PaymentReconciliationService $reconciliation,
        PaymentReviewResolutionService $reviews,
        PaymentReconciliationQuery $queue,
        ActivityLogger $activities,
    ): RedirectResponse {
        if (! in_array($payment->status, Payment::UNSAFE_RETRY_STATUSES, true)) {
            return back()->with('warning', 'Giao dịch đã ở trạng thái kết thúc; hệ thống không cho phép ghi đè kết quả.');
        }
        if ($payment->status === Payment::STATUS_REVIEW
            && ! in_array($payment->provider, Payment::SUPPORTED_PROVIDERS, true)) {
            return back()->with('warning', 'Provider này chưa hỗ trợ truy vấn lại giao dịch ở trạng thái review. Hệ thống giữ nguyên dữ liệu để điều tra.');
        }

        $this->assertRateLimit('reconcile', $request, $payment, 6);
        $before = $payment->status;

        try {
            if ($payment->status === Payment::STATUS_REVIEW) {
                $result = $reviews->resolve($payment, $request->user());
                $category = $result->category;
            } else {
                $category = $reconciliation->reconcile($payment);
            }
        } catch (LogicException|PaymentConfigurationException|PaymentInitiationException|PayOsResponseException|PayOsTransportException|VnpayResponseException|VnpayTransportException|ZaloPayResponseException|ZaloPayTransportException $exception) {
            report($exception);

            return back()->with('error', 'Không thể hoàn tất đối soát bằng bằng chứng provider hiện có. Giao dịch không bị ép sang thành công.');
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
            'result' => $category,
        ]);
        $this->logReviewTransition($activities, $payment, $before);
        $queue->forgetBadge();

        return back()->with('success', 'Đã hoàn tất lượt đối soát provider. Trạng thái hiện tại: '.StatusLabel::for('payment', $payment->status).'.');
    }

    private function assertRateLimit(string $action, Request $request, Payment $payment, int $maxAttempts): void
    {
        $key = implode(':', ['admin-payment', $action, $request->user()->id, $payment->id]);
        abort_if(RateLimiter::tooManyAttempts($key, $maxAttempts), 429);
        RateLimiter::hit($key, 60);
    }

    private function logReviewTransition(ActivityLogger $activities, Payment $payment, string $before): void
    {
        if ($before !== Payment::STATUS_REVIEW && $payment->status === Payment::STATUS_REVIEW) {
            $activities->log('payment.review_entered', $payment, [
                'payment_status' => $before,
            ], [
                'payment_status' => $payment->status,
            ], [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'provider' => $payment->provider,
                'reason' => $payment->failure_reason,
            ]);
        }

        if ($before === Payment::STATUS_REVIEW
            && $payment->status === Payment::STATUS_SUCCESS
            && $payment->verified_at !== null) {
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
    }
}
