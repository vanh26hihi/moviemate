<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\PaymentConfigurationException;
use App\Exceptions\PaymentInitiationException;
use App\Exceptions\PayOsResponseException;
use App\Exceptions\PayOsTransportException;
use App\Exceptions\VnpayResponseException;
use App\Exceptions\VnpayTransportException;
use App\Exceptions\ZaloPayResponseException;
use App\Exceptions\ZaloPayTransportException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\BookingExpirationService;
use App\Services\Counter\CounterBookingService;
use App\Services\Payments\PaymentInitiationService;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Payments\PaymentResumeService;
use App\Services\Payments\PayOsCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CounterPaymentController extends Controller
{
    public function initiate(
        Request $request,
        Booking $booking,
        string $provider,
        CounterBookingService $counter,
        PaymentInitiationService $payments,
        ActivityLogger $activities,
    ): RedirectResponse {
        abort_unless(in_array($provider, ['vnpay', 'payos'], true), 404);
        $request->validate([
            'amount' => ['prohibited'],
            'sales_channel' => ['prohibited'],
            'created_by_staff_id' => ['prohibited'],
            'settled_at' => ['prohibited'],
            'settled_by_user_id' => ['prohibited'],
        ]);
        $booking = $counter->authorized($request->user(), $booking);

        try {
            $result = $payments->initiate($booking, $provider, $request->ip() ?? '127.0.0.1');
        } catch (PaymentConfigurationException|PaymentInitiationException|PayOsResponseException|PayOsTransportException|VnpayResponseException|VnpayTransportException) {
            return redirect()->route('staff.counter.payment-result', $booking)
                ->with('warning', $provider === 'vnpay'
                    ? 'Chưa thể mở thanh toán VNPAY. Vui lòng kiểm tra trạng thái giao dịch hiện tại.'
                    : 'Chưa thể mở thanh toán payOS. Hệ thống vẫn giữ trạng thái giao dịch an toàn.');
        }

        if (! $result->replayed) {
            $activities->log(
                'counter.provider_payment_initiated',
                $result->payment,
                [],
                ['status' => $result->payment->status],
                [
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'cinema_id' => $booking->cinema_id,
                    'sales_channel' => $booking->sales_channel,
                    'provider' => $provider,
                    'amount' => (int) $result->payment->amount,
                    'initiated_by_user_id' => $request->user()->id,
                ],
            );
        }

        if (! is_string($result->orderUrl) || $result->orderUrl === '') {
            return redirect()->route('staff.counter.payment-result', $booking)
                ->with('warning', 'Giao dịch đã được tạo nhưng chưa có đường dẫn thanh toán an toàn.');
        }

        return redirect()->away($result->orderUrl);
    }

    public function resume(
        Request $request,
        Booking $booking,
        CounterBookingService $counter,
        PaymentResumeService $payments,
        ActivityLogger $activities,
    ): RedirectResponse {
        $request->validate([
            'payment_id' => ['prohibited'],
            'provider' => ['prohibited'],
            'amount' => ['prohibited'],
            'sales_channel' => ['prohibited'],
        ]);
        $booking = $counter->authorized($request->user(), $booking);
        $payment = $booking->payments->sortByDesc('id')->first();
        abort_unless($payment && in_array($payment->provider, ['vnpay', 'payos'], true), 409);

        try {
            $result = $payments->resume($booking, $request->ip() ?? '127.0.0.1');
        } catch (PaymentConfigurationException|PaymentInitiationException) {
            return redirect()->route('staff.counter.payment-result', $booking)
                ->with('warning', 'Giao dịch hiện tại không thể tiếp tục. Vui lòng xem trạng thái xác minh trước khi thao tác thêm.');
        }

        $activities->log(
            'counter.provider_payment_resumed',
            $result->payment,
            [],
            ['status' => $result->payment->status],
            [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'cinema_id' => $booking->cinema_id,
                'sales_channel' => $booking->sales_channel,
                'provider' => $result->payment->provider,
                'amount' => (int) $result->payment->amount,
                'initiated_by_user_id' => $request->user()->id,
            ],
        );

        abort_unless(is_string($result->orderUrl) && $result->orderUrl !== '', 409);

        return redirect()->away($result->orderUrl);
    }

    public function reconcile(
        Request $request,
        Booking $booking,
        CounterBookingService $counter,
        PaymentReconciliationService $reconciliation,
        ActivityLogger $activities,
    ): RedirectResponse {
        $request->validate([
            'payment_id' => ['prohibited'],
            'provider' => ['prohibited'],
            'status' => ['prohibited'],
        ]);
        $booking = $counter->authorized($request->user(), $booking);
        $activeAttempts = $booking->payments
            ->filter(fn (Payment $attempt): bool => in_array($attempt->status, Payment::UNSAFE_RETRY_STATUSES, true))
            ->sortByDesc('id')
            ->values();
        abort_unless($activeAttempts->count() === 1, 409);

        /** @var Payment $payment */
        $payment = $activeAttempts->first();
        abort_unless(in_array($payment->status, Payment::RECONCILABLE_STATUSES, true), 409);

        try {
            $status = $reconciliation->reconcile($payment);
        } catch (PaymentConfigurationException|PaymentInitiationException|PayOsResponseException|PayOsTransportException|VnpayResponseException|VnpayTransportException|ZaloPayResponseException|ZaloPayTransportException) {
            return redirect()->route('staff.counter.payment-result', $booking)
                ->with('warning', 'Chưa thể xác minh với nhà cung cấp. Giao dịch và ghế vẫn được giữ an toàn; không tạo lần thanh toán mới.');
        }

        $activities->log(
            'counter.provider_payment_reconciled',
            $payment,
            [],
            ['status' => $status],
            [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'cinema_id' => $booking->cinema_id,
                'provider' => $payment->provider,
                'payment_id' => $payment->id,
                'checked_by_user_id' => $request->user()->id,
            ],
        );

        return in_array($status, [Payment::STATUS_FAILED, Payment::STATUS_EXPIRED], true)
            ? redirect()->route('staff.counter.review', $booking)
                ->with('success', 'Nhà cung cấp xác nhận giao dịch không thành công. Ghế vẫn được giữ trong thời hạn đơn; hãy chọn phương thức khác hoặc hủy đơn.')
            : redirect()->route('staff.counter.payment-result', $booking);
    }

    public function cancelPayOsAttempt(
        Request $request,
        Booking $booking,
        CounterBookingService $counter,
        ActivityLogger $activities,
    ): RedirectResponse {
        $request->validate([
            'payment_id' => ['prohibited'],
            'provider' => ['prohibited'],
        ]);
        $booking = $counter->authorized($request->user(), $booking);
        $activeAttempts = $booking->payments
            ->filter(fn (Payment $attempt): bool => in_array($attempt->status, Payment::UNSAFE_RETRY_STATUSES, true))
            ->sortByDesc('id')
            ->values();
        abort_unless($activeAttempts->count() === 1, 409);

        /** @var Payment $payment */
        $payment = $activeAttempts->first();
        abort_unless($payment->provider === 'payos' && $payment->status === Payment::STATUS_PENDING, 409);

        try {
            $status = app(PayOsCancellationService::class)->cancel($payment);
        } catch (PaymentConfigurationException|PaymentInitiationException|PayOsResponseException|PayOsTransportException) {
            return redirect()->route('staff.counter.payment-result', $booking)
                ->with('warning', 'payOS chưa xác nhận hủy giao dịch. Ghế vẫn được giữ an toàn và chưa thể chuyển sang phương thức khác.');
        }

        if ($status !== Payment::STATUS_FAILED) {
            return redirect()->route('staff.counter.payment-result', $booking)
                ->with('warning', 'payOS chưa trả trạng thái hủy cuối cùng. Không tạo thêm giao dịch để tránh thu tiền hai lần.');
        }

        $activities->log(
            'counter.provider_payment_cancelled_for_switch',
            $payment,
            [
                'status' => Payment::STATUS_PENDING,
            ],
            ['status' => Payment::STATUS_FAILED],
            [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'cinema_id' => $booking->cinema_id,
                'provider' => 'payos',
                'payment_id' => $payment->id,
                'cancelled_by_user_id' => $request->user()->id,
            ],
        );

        return redirect()->route('staff.counter.review', $booking)
            ->with('success', 'payOS đã xác nhận hủy giao dịch. Ghế vẫn được giữ trong thời hạn đơn; bạn có thể chọn phương thức khác.');
    }

    public function result(
        Request $request,
        Booking $booking,
        CounterBookingService $counter,
        BookingExpirationService $expiration,
    ): View {
        $booking = $counter->authorized($request->user(), $booking);
        if ($booking->booking_status === 'pending_payment'
            && $booking->payment_status === 'unpaid'
            && $booking->expires_at?->isPast() === true) {
            $expiration->expire((int) $booking->id);
            $booking->refresh();
        }
        $payment = $booking->payments->sortByDesc('id')->first();
        $authoritative = $booking->payments
            ->filter(fn (Payment $attempt): bool => $attempt->hasAuthoritativeSuccessEvidence())
            ->sortByDesc('id')
            ->first();
        $payment ??= $authoritative;
        $returnedFromProvider = $request->boolean('returned');
        $state = match (true) {
            $booking->booking_status === 'paid'
                && $booking->payment_status === 'paid'
                && $authoritative !== null => 'paid',
            $booking->booking_status === 'cancelled' => 'cancelled',
            $payment?->status === Payment::STATUS_REVIEW => 'review',
            $payment?->status === Payment::STATUS_PROCESSING => 'processing',
            $payment?->status === Payment::STATUS_UNRESOLVED => 'processing',
            $returnedFromProvider && $payment?->status === Payment::STATUS_PENDING => 'processing',
            in_array($payment?->status, [Payment::STATUS_FAILED, Payment::STATUS_EXPIRED], true) => 'failed',
            default => 'pending',
        };
        $printState = $booking->ticketPrint;
        $canAutoPrint = $state === 'paid'
            && $request->user()->hasPermission('tickets.print')
            && $printState === null;
        $canResume = in_array($state, ['pending', 'processing'], true)
            && $payment?->status === Payment::STATUS_PENDING
            && in_array($payment?->provider, ['vnpay', 'payos'], true)
            && $payment?->expires_at?->isFuture() === true;
        $activeAttempts = $booking->payments
            ->filter(fn (Payment $attempt): bool => in_array($attempt->status, Payment::UNSAFE_RETRY_STATUSES, true))
            ->sortByDesc('id')
            ->values();
        $activeAttempt = $activeAttempts->count() === 1 ? $activeAttempts->first() : null;
        $bookingIsPending = $booking->booking_status === 'pending_payment'
            && $booking->payment_status === 'unpaid'
            && $booking->expires_at?->isFuture() === true
            && $authoritative === null;
        $canReconcile = $bookingIsPending
            && $activeAttempt !== null
            && in_array($activeAttempt->status, Payment::RECONCILABLE_STATUSES, true);
        $canCancelPayOsAttempt = $bookingIsPending
            && $activeAttempt?->provider === 'payos'
            && $activeAttempt?->status === Payment::STATUS_PENDING;
        $canCancelOrder = $bookingIsPending
            && $activeAttempts->count() <= 1
            && ($activeAttempt === null
                || ($activeAttempt->status === Payment::STATUS_PENDING
                    && in_array($activeAttempt->provider, ['vnpay', 'payos'], true)));
        $canChooseAnotherMethod = $bookingIsPending
            && in_array($state, ['failed', 'pending'], true)
            && $activeAttempts->isEmpty();

        return view('staff.counter.payment-result', compact(
            'booking', 'payment', 'authoritative', 'state', 'printState', 'canAutoPrint', 'canResume',
            'canReconcile', 'canCancelPayOsAttempt', 'canCancelOrder', 'canChooseAnotherMethod',
        ));
    }
}
