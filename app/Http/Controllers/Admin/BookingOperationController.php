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
        AdminTicketEmailService $ticketEmails,
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can(
                'ticket_deliveries.retry'
            ),
            403
        );
    
        $this->assertRateLimit(
            'ticket-resend',
            $request,
            $booking->id,
            3
        );
    
        try {
            $delivery = $ticketEmails->send(
                $booking,
                $request->user()?->id,
            );
        } catch (Throwable $exception) {
            report($exception);
    
            $message = match (
                $exception->getMessage()
            ) {
                'Khách hàng chưa có email hợp lệ để nhận vé.'
                    => 'Khách hàng chưa có email hợp lệ để nhận vé.',
    
                'ticket_booking_not_eligible'
                    => 'Đơn đặt vé hiện tại chưa đủ điều kiện để gửi vé.',
    
                default
                    => 'Không thể gửi vé qua email lúc này. Hệ thống đã ghi nhận lỗi.',
            };
    
            return redirect()
                ->route(
                    'admin.bookings.show',
                    $booking
                )
                ->with(
                    'error',
                    $message
                );
        }
    
        return redirect()
            ->route(
                'admin.bookings.show',
                $booking
            )
            ->with(
                'success',
                $delivery->status === 'processing'
                    ? 'Yêu cầu gửi vé đang được hệ thống xử lý.'
                    : 'Đã đưa vé vào hàng đợi gửi email cho khách hàng.'
            );
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
