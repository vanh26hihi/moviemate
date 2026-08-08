<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Admin\AdminTicketDeliveryQuery;
use App\Services\GuestBookingAccessService;
use App\Services\Tickets\BookingTicketEligibility;
use App\Services\Tickets\TicketDeliveryOutbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class TicketEmailResendController extends Controller
{
    public function __invoke(
        Request $request,
        Booking $booking,
        GuestBookingAccessService $guestAccess,
        BookingTicketEligibility $eligibility,
        TicketDeliveryOutbox $deliveries,
        AdminTicketDeliveryQuery $deliveryQuery,
    ): RedirectResponse {
        if (Auth::check()) {
            abort_unless($booking->user_id === Auth::id(), 403);
        } else {
            abort_unless($guestAccess->allows($request, $booking), 404);
        }

        abort_unless($eligibility->isUsable($booking), 404);

        $deliveries->requestResend($booking, Auth::id());
        $deliveryQuery->forgetBadge();

        return back()->with('success', 'Yêu cầu gửi lại vé đã được ghi nhận.');
    }
}
