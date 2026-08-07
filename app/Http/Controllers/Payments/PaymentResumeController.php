<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentConfigurationException;
use App\Exceptions\PaymentInitiationException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\GuestBookingAccessService;
use App\Services\Payments\PaymentResumeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class PaymentResumeController extends Controller
{
    public function __invoke(
        Request $request,
        Booking $booking,
        GuestBookingAccessService $guestAccess,
        PaymentResumeService $payments,
    ): RedirectResponse {
        $this->authorizeBooking($request, $booking, $guestAccess);

        try {
            $result = $payments->resume($booking, $request->ip() ?? '127.0.0.1');
        } catch (PaymentConfigurationException|PaymentInitiationException) {
            $booking->refresh();
            if ($booking->booking_status === 'expired') {
                return to_route('user.bookings.expired', $booking)
                    ->with('warning', 'Thời gian giữ ghế đã hết. Vui lòng chọn ghế lại.');
            }

            $payment = $booking->payments()->latest('id')->first();
            $route = match (true) {
                $booking->booking_status === 'cancelled' => 'user.bookings.success',
                $booking->payment_status === 'paid' => 'user.bookings.success',
                $payment?->status === Payment::STATUS_REVIEW => 'user.bookings.payment-review',
                default => 'user.bookings.pending',
            };
            $message = $payment?->status === Payment::STATUS_REVIEW
                ? 'Giao dịch cần được hỗ trợ. MovieMate sẽ cập nhật khi có kết quả chính thức.'
                : 'MovieMate đang xác minh kết quả thanh toán. Vui lòng không tạo thêm giao dịch cho đơn này.';

            return to_route($route, $booking)->with('warning', $message);
        }

        return redirect()->away($result->orderUrl);
    }

    private function authorizeBooking(
        Request $request,
        Booking $booking,
        GuestBookingAccessService $guestAccess,
    ): void {
        if (Auth::check()) {
            Gate::authorize('view', $booking);

            return;
        }

        abort_unless($guestAccess->allows($request, $booking), 404);
    }
}
