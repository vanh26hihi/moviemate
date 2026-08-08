<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\Admin\AdminTicketDeliveryQuery;
use App\Services\BookingCancellationService;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Tickets\BookingTicketEligibility;
use App\Services\Tickets\TicketDeliveryRetryService;
use App\Support\PrivacyMask;
use App\Support\StatusLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class BookingOperationController extends Controller
{
    public function resendTicket(
        Request $request,
        Booking $booking,
        BookingTicketEligibility $eligibility,
        TicketDeliveryRetryService $retries,
        ActivityLogger $activities,
        AdminTicketDeliveryQuery $deliveryQuery,
    ): RedirectResponse {
        $booking->load(['payments', 'ticketDelivery']);
        abort_unless($eligibility->isDeliverable($booking), 404);
        abort_unless($booking->ticketDelivery, 404);
        if ($booking->ticketDelivery->status !== BookingTicketDelivery::STATUS_FAILED) {
            return back()->with('warning', 'Chỉ vé gửi lỗi mới có thể được đưa lại vào hàng đợi.');
        }
        $this->assertRateLimit('ticket-resend', $request, $booking->id, 3);

        $before = $booking->ticketDelivery->status;
        $result = $retries->retry($booking->ticketDelivery);
        if (! $result->changed) {
            $message = match ($result->category) {
                'sent' => 'Vé đã được gửi thành công; hệ thống không tạo lượt gửi trùng.',
                'active_claim' => 'Vé đang được tiến trình khác gửi.',
                'already_queued' => 'Vé đã nằm trong hàng đợi gửi.',
                default => 'Đơn không còn đủ điều kiện gửi lại vé.',
            };

            return back()->with('warning', $message);
        }

        $activities->log('booking.ticket_resend_requested', $booking, [
            'delivery_status' => $before,
        ], [
            'delivery_status' => $result->delivery->status,
        ], [
            'booking_id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'recipient_mask' => PrivacyMask::email($booking->recipient_email),
        ]);
        $deliveryQuery->forgetBadge();

        return back()->with('success', 'Yêu cầu gửi lại vé đã được ghi nhận.');
    }

    public function queryPayment(
        Request $request,
        Booking $booking,
        PaymentReconciliationService $reconciliation,
        ActivityLogger $activities,
    ): RedirectResponse {
        $payment = $booking->payments()
            ->whereIn('status', Payment::RECONCILABLE_STATUSES)
            ->latest('id')
            ->first();

        if (! $payment) {
            return back()->with('warning', 'Đơn này không có giao dịch đang chờ nhà cung cấp để truy vấn.');
        }

        $this->assertRateLimit('payment-query', $request, $payment->id, 6);
        $before = $payment->status;

        try {
            $result = $reconciliation->reconcile($payment);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Chưa thể nhận kết quả chắc chắn từ nhà cung cấp. Vui lòng thử lại sau.');
        }

        $payment->refresh();
        $activities->log('booking.payment_query_requested', $booking, [
            'payment_status' => $before,
        ], [
            'payment_status' => $payment->status,
        ], [
            'booking_id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'payment_id' => $payment->id,
            'provider' => $payment->provider,
            'result' => $result,
        ]);

        return back()->with('success', 'Đã truy vấn nhà cung cấp. Kết quả hiện tại: '.StatusLabel::for('payment', $payment->status).'.');
    }

    public function cancel(
        Request $request,
        Booking $booking,
        BookingCancellationService $cancellations,
    ): RedirectResponse {
        $result = $cancellations->cancel($booking->id);

        if ($result->cancelled) {
            return back()->with('success', 'Đơn đặt vé đã được hủy an toàn.');
        }

        return back()->with('warning', $result->alreadyCancelled
            ? 'Đơn đặt vé đã được hủy trước đó.'
            : 'Đơn đặt vé này không thể hủy ở trạng thái hiện tại.');
    }

    private function assertRateLimit(string $action, Request $request, int $subjectId, int $maxAttempts): void
    {
        $key = implode(':', ['admin-booking', $action, $request->user()->id, $subjectId]);
        abort_if(RateLimiter::tooManyAttempts($key, $maxAttempts), 429);
        RateLimiter::hit($key, 60);
    }
}
