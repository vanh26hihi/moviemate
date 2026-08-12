<?php

namespace App\Http\Controllers\User;

use App\Exceptions\FoodSelectionValidationException;
use App\Exceptions\PaymentInitiationException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\BookingCheckoutDraftService;
use App\Services\BookingCheckoutPreviewService;
use App\Services\GuestBookingAccessService;
use App\Services\UnifiedBookingCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingCheckoutConfirmController extends Controller
{
    public function __invoke(
        Request $request,
        BookingCheckoutDraftService $drafts,
        BookingCheckoutPreviewService $previews,
        UnifiedBookingCheckoutService $checkout,
        GuestBookingAccessService $guestAccess,
    ): RedirectResponse {
        $request->validate([
            'showtime_id' => ['prohibited'],
            'seat_ids' => ['prohibited'],
            'food_items' => ['prohibited'],
            'pickup_cinema_id' => ['prohibited'],
            'seat_subtotal' => ['prohibited'],
            'food_subtotal' => ['prohibited'],
            'total_amount' => ['prohibited'],
            'payment_status' => ['prohibited'],
            'payment_method' => ['nullable', 'string', 'in:vnpay,zalopay,payos'],
        ]);

        $draft = $drafts->current($request, true);
        $provider = (string) $request->input('payment_method', config('payment.driver', 'vnpay'));

        try {
            $drafts->assertMayCreateHold($request, $draft);
            $previews->preview($draft);
            $result = $checkout->confirm(
                $draft,
                $request->user()?->getAuthIdentifier(),
                $provider,
                $request->ip(),
            );
            $drafts->rememberActiveHold($request, $result->checkout->booking);
        } catch (FoodSelectionValidationException $exception) {
            return redirect()
                ->route('user.bookings.review')
                ->withErrors(['food_items' => $exception->getMessage()])
                ->withInput();
        } catch (PaymentInitiationException $exception) {
            return redirect()
                ->route('user.bookings.review')
                ->withErrors(['payment_method' => match ($provider) {
                    'vnpay' => 'Không thể khởi tạo thanh toán VNPAY. Vui lòng thử lại hoặc liên hệ hỗ trợ.',
                    'payos' => 'Chưa thể kết nối tới phương thức thanh toán này. Vui lòng thử lại.',
                    default => $exception->getMessage(),
                }])
                ->withInput();
        }

        $booking = $result->checkout->booking;

        if ($result->checkout->guestAccessToken !== null) {
            abort_unless(
                $guestAccess->exchange($request, $booking, $result->checkout->guestAccessToken),
                404,
            );
        }

        if (is_string($result->orderUrl) && $result->orderUrl !== '') {
            return redirect()->away($result->orderUrl);
        }

        $statusRoute = match ($result->payment?->status) {
            Payment::STATUS_SUCCESS => 'user.bookings.success',
            Payment::STATUS_FAILED => 'user.bookings.failed',
            Payment::STATUS_REVIEW => 'user.bookings.payment-review',
            Payment::STATUS_EXPIRED => 'user.bookings.expired',
            Payment::STATUS_UNRESOLVED => 'user.bookings.pending',
            default => 'user.bookings.pending',
        };

        if ($result->payment?->status === Payment::STATUS_SUCCESS) {
            return redirect()->route($statusRoute, $booking);
        }

        return redirect()
            ->route($statusRoute, $booking)
            ->with('warning', match ($provider) {
                'vnpay' => 'Không thể khởi tạo thanh toán VNPAY. Vui lòng thử lại hoặc liên hệ hỗ trợ.',
                'payos' => 'Chưa thể xác minh giao dịch lúc này. Hệ thống tiếp tục giữ trạng thái an toàn và bạn có thể kiểm tra lại sau.',
                default => 'Yêu cầu ZaloPay chưa xác định. MovieMate đang đối soát lần thanh toán hiện tại và không tạo lần mới.',
            });
    }
}
