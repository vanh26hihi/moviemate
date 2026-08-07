<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\PaymentConfigurationException;
use App\Exceptions\PaymentInitiationException;
use App\Exceptions\PayOsResponseException;
use App\Exceptions\PayOsTransportException;
use App\Exceptions\VnpayResponseException;
use App\Exceptions\VnpayTransportException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\Counter\CounterBookingService;
use App\Services\Payments\PaymentInitiationService;
use App\Services\Payments\PaymentResumeService;
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

    public function result(
        Request $request,
        Booking $booking,
        CounterBookingService $counter,
    ): View {
        $booking = $counter->authorized($request->user(), $booking);
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

        return view('staff.counter.payment-result', compact(
            'booking', 'payment', 'authoritative', 'state', 'printState', 'canAutoPrint', 'canResume',
        ));
    }
}
