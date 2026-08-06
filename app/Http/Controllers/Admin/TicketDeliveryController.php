<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTicketDeliveryRequest;
use App\Models\BookingTicketDelivery;
use App\Services\ActivityLogger;
use App\Services\Admin\AdminTicketDeliveryDetailService;
use App\Services\Admin\AdminTicketDeliveryQuery;
use App\Services\Tickets\TicketDeliveryRetryService;
use App\Support\PrivacyMask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

final class TicketDeliveryController extends Controller
{
    public function index(IndexTicketDeliveryRequest $request, AdminTicketDeliveryQuery $deliveries): View
    {
        $filters = $request->validated();

        return view('admin.ticket-deliveries.index', [
            'deliveries' => $deliveries->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function show(
        Request $request,
        BookingTicketDelivery $ticketDelivery,
        AdminTicketDeliveryDetailService $details,
    ): View {
        $data = $details->get(
            $ticketDelivery,
            $request->user()?->can('activity_logs.view') === true,
        );
        $data['retryAllowed'] = $data['retryAllowed']
            && ! RateLimiter::tooManyAttempts($this->retryRateLimitKey($request, $ticketDelivery), 3);

        return view('admin.ticket-deliveries.show', $data);
    }

    public function retry(
        Request $request,
        BookingTicketDelivery $ticketDelivery,
        TicketDeliveryRetryService $retries,
        AdminTicketDeliveryQuery $query,
        ActivityLogger $activities,
    ): RedirectResponse {
        $key = $this->retryRateLimitKey($request, $ticketDelivery);
        abort_if(RateLimiter::tooManyAttempts($key, 3), 429);
        RateLimiter::hit($key, 60);

        $before = $ticketDelivery->status;
        $result = $retries->retry($ticketDelivery);
        if (! $result->changed) {
            $message = match ($result->category) {
                'sent' => 'Vé đã được gửi thành công; hệ thống không tạo lượt gửi trùng.',
                'active_claim' => 'Vé đang được tiến trình khác gửi. Vui lòng chờ claim hiện tại kết thúc.',
                'already_queued' => 'Vé đã nằm trong hàng đợi gửi.',
                default => 'Đơn không còn đủ điều kiện gửi vé.',
            };

            return back()->with('warning', $message);
        }

        $action = $result->category === 'expired_claim_released'
            ? 'ticket_delivery.expired_claim_released'
            : 'ticket_delivery.retry_requested';
        $activities->log($action, $result->delivery, [
            'delivery_status' => $before,
        ], [
            'delivery_status' => $result->delivery->status,
        ], [
            'booking_id' => $result->delivery->booking_id,
            'delivery_id' => $result->delivery->id,
            'attempt_number' => $result->delivery->attempts,
            'recipient_mask' => PrivacyMask::email($result->delivery->booking?->recipient_email),
        ]);
        $query->forgetBadge();

        return back()->with('success', $result->category === 'expired_claim_released'
            ? 'Claim quá hạn đã được giải phóng và vé được đưa lại vào hàng đợi.'
            : 'Yêu cầu thử gửi lại vé đã được ghi nhận.');
    }

    private function retryRateLimitKey(Request $request, BookingTicketDelivery $delivery): string
    {
        return implode(':', ['admin-ticket-delivery', 'retry', $request->user()->id, $delivery->id]);
    }
}
