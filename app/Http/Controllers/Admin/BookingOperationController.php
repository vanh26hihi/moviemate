<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\Admin\AdminTicketDeliveryQuery;
use App\Services\BookingCancellationService;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Tickets\BookingTicketEligibility;
use App\Services\Tickets\TicketCheckinCapability;
use App\Services\Tickets\TicketDeliveryOutbox;
use App\Support\PrivacyMask;
use App\Support\StatusLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

final class BookingOperationController extends Controller
{
    public function resendTicket(
        Request $request,
        Booking $booking,
        BookingTicketEligibility $eligibility,
        TicketDeliveryOutbox $deliveries,
        ActivityLogger $activities,
        AdminTicketDeliveryQuery $deliveryQuery,
    ): RedirectResponse {
        $booking->load('payments');
        abort_unless($eligibility->isDeliverable($booking), 404);
        $this->assertRateLimit('ticket-resend', $request, $booking->id, 3);

        DB::transaction(function () use ($request, $booking, $deliveries, $activities): void {
            $before = $booking->ticketDelivery()->value('status');
            $delivery = $deliveries->requestResend($booking, $request->user()->id);
            $activities->log('booking.ticket_resend_requested', $booking, [
                'delivery_status' => $before,
            ], [
                'delivery_status' => $delivery->status,
            ], [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'recipient_mask' => PrivacyMask::email($booking->recipient_email),
            ]);
        });
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
        ActivityLogger $activities,
    ): RedirectResponse {
        $result = DB::transaction(function () use ($booking, $cancellations, $activities) {
            $before = $booking->booking_status;
            $result = $cancellations->cancel($booking->id);

            if ($result->cancelled) {
                $activities->log('booking.cancelled', $booking, [
                    'status' => $before,
                ], [
                    'status' => 'cancelled',
                ], [
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'source' => 'admin_booking_operations',
                ]);
            }

            return $result;
        });

        if ($result->cancelled) {
            return back()->with('success', 'Đơn đặt vé đã được hủy an toàn.');
        }

        return back()->with('warning', $result->alreadyCancelled
            ? 'Đơn đặt vé đã được hủy trước đó.'
            : 'Đơn đặt vé này không thể hủy ở trạng thái hiện tại.');
    }

    public function print(
        Booking $booking,
        BookingTicketEligibility $eligibility,
        TicketCheckinCapability $checkinCapabilities,
    ): View {
        $booking->load([
            'user', 'payments', 'showtime.movie', 'showtime.cinema', 'showtime.room',
            'bookingSeats.seat', 'foodOrder.items',
        ]);
        abort_unless($eligibility->isPrintable($booking), 404);

        return view('user.bookings.ticket', [
            'booking' => $booking,
            'isUsable' => $eligibility->isUsable($booking),
            'isPrintable' => true,
            'verifiedPayment' => $eligibility->verifiedPayment($booking),
            'printMode' => true,
            'backUrl' => route('admin.bookings.show', $booking),
            'backLabel' => 'Về chi tiết đơn',
            'ticketRecipient' => PrivacyMask::email($booking->recipient_email),
            'checkinCapability' => $eligibility->isUsable($booking) ? $checkinCapabilities->issue($booking) : null,
        ]);
    }

    private function assertRateLimit(string $action, Request $request, int $subjectId, int $maxAttempts): void
    {
        $key = implode(':', ['admin-booking', $action, $request->user()->id, $subjectId]);
        abort_if(RateLimiter::tooManyAttempts($key, $maxAttempts), 429);
        RateLimiter::hit($key, 60);
    }
}
