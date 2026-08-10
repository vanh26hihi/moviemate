<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentConfigurationException;
use App\Exceptions\PaymentInitiationException;
use App\Exceptions\PayOsResponseException;
use App\Exceptions\PayOsTransportException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\PayOsCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class PayOsCancellationController extends Controller
{
    public function __invoke(Booking $booking, PayOsCancellationService $cancellations): RedirectResponse
    {
        Gate::authorize('cancel', $booking);
        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('provider', 'payos')
            ->where('status', Payment::STATUS_PENDING)
            ->latest('id')->firstOrFail();

        try {
            $status = $cancellations->cancel($payment);
        } catch (PaymentConfigurationException|PayOsTransportException|PayOsResponseException|PaymentInitiationException) {
            return back()->with('warning', 'Chưa thể xác minh việc hủy với payOS. Ghế vẫn được giữ an toàn và bạn có thể kiểm tra lại sau.');
        }

        return $status === Payment::STATUS_FAILED
            ? to_route('user.bookings.history')->with('success', 'Thanh toán đã được hủy và ghế đã được giải phóng.')
            : back()->with('warning', 'payOS chưa xác nhận hủy. Đơn đặt vé được giữ nguyên.');
    }
}
