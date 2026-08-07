<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class BookingCancellationController extends Controller
{
    public function __invoke(
        Booking $booking,
        BookingCancellationService $cancellations,
    ): RedirectResponse {
        Gate::authorize('cancel', $booking);

        $result = $cancellations->cancelCustomer($booking->id);

        if ($result->cancelled) {
            return to_route('user.bookings.history')
                ->with('success', 'Đã hủy đơn đặt vé và giải phóng các ghế đang giữ.');
        }

        if ($result->alreadyCancelled) {
            return to_route('user.bookings.history')
                ->with('warning', 'Đơn đặt vé đã được hủy trước đó.');
        }

        return to_route('user.bookings.history')
            ->with('warning', 'Đơn đặt vé này không thể hủy ở trạng thái hiện tại.');
    }
}
