<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Mail\BookingTicketMail;
use App\Models\Booking;
use App\Services\LoyaltyPointService;
use App\Services\PayosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PayosController extends Controller
{
    public function return(Request $request, Booking $booking, PayosService $payos): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()?->id, 403);

        $booking->load('payment');
        $orderCode = $booking->payment?->provider_order_code ?: $booking->id;

        try {
            $paymentInfo = $payos->getPaymentInfo($orderCode);

            if ($payos->isPaidStatus($paymentInfo['status'] ?? null)) {
                $wasMarkedPaid = $this->markBookingPaid($booking, $paymentInfo);

                if ($wasMarkedPaid) {
                    $this->sendTicketEmail($booking);
                }

                return redirect()->route('user.bookings.success', $booking)
                    ->with('success', 'Thanh toán payOS thành công.');
            }
        } catch (\Throwable $exception) {
            Log::warning('payOS return check failed', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('user.bookings.history')
            ->with('error', 'Chưa xác nhận được thanh toán. Nếu bạn đã chuyển khoản, hệ thống sẽ tự cập nhật sau khi nhận webhook từ payOS.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()?->id, 403);

        DB::transaction(function () use ($booking) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->payment_status === 'paid') {
                return;
            }

            $booking->update([
                'payment_status' => 'failed',
                'booking_status' => 'cancelled',
            ]);

            $booking->payment?->update([
                'status' => 'failed',
            ]);
            $booking->foodOrder()->update(['status' => 'cancelled']);

            app(LoyaltyPointService::class)->restoreRedeemedPoints($booking);

            $booking->bookingSeats()->delete();
        });

        return redirect()->route('user.bookings.history')
            ->with('error', 'Bạn đã hủy thanh toán payOS.');
    }

    public function webhook(Request $request, PayosService $payos): JsonResponse
    {
        try {
            $data = $payos->verifyWebhook($request->all());
        } catch (\Throwable $exception) {
            Log::warning('Invalid payOS webhook', ['message' => $exception->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Invalid webhook'], 400);
        }

        $webhookCode = $request->input('code') ?? ($data['code'] ?? null);

        if ($webhookCode !== '00' || ! $request->boolean('success')) {
            return response()->json(['success' => true]);
        }

        $booking = Booking::with('payment')->find((int) ($data['orderCode'] ?? 0));

        if (! $booking) {
            Log::info('payOS webhook ignored because booking was not found', [
                'orderCode' => $data['orderCode'] ?? null,
            ]);

            return response()->json(['success' => true]);
        }

        if ($this->markBookingPaid($booking, $data)) {
            $this->sendTicketEmail($booking);
        }

        return response()->json(['success' => true]);
    }

    private function markBookingPaid(Booking $booking, array $data): bool
    {
        return DB::transaction(function () use ($booking, $data) {
            $booking = Booking::whereKey($booking->id)
                ->with('payment')
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->payment_status === 'paid') {
                return false;
            }

            if ($booking->booking_status === 'pending' && $booking->hold_expires_at && $booking->hold_expires_at->isPast()) {
                $booking->update(['booking_status' => 'expired', 'payment_status' => 'failed']);
                $booking->payment?->update(['status' => 'failed']);
                $booking->foodOrder()->update(['status' => 'cancelled']);
                app(LoyaltyPointService::class)->restoreRedeemedPoints($booking);
                $booking->bookingSeats()->delete();

                Log::warning('payOS payment ignored because the seat hold expired', ['booking_id' => $booking->id]);

                return false;
            }

            if (in_array($booking->booking_status, ['cancelled', 'expired'], true)
                || in_array($booking->payment_status, ['failed', 'refunded'], true)) {
                Log::warning('payOS paid webhook ignored because booking is not payable anymore', [
                    'booking_id' => $booking->id,
                    'booking_status' => $booking->booking_status,
                    'payment_status' => $booking->payment_status,
                ]);

                return false;
            }

            $amount = (int) ($data['amount'] ?? $data['amountPaid'] ?? 0);

            if ($amount > 0 && $amount !== (int) round((float) $booking->total_amount)) {
                Log::warning('payOS amount mismatch', [
                    'booking_id' => $booking->id,
                    'booking_amount' => (int) round((float) $booking->total_amount),
                    'payos_amount' => $amount,
                ]);

                return false;
            }

            $booking->update([
                'payment_status' => 'paid',
                'booking_status' => 'paid',
            ]);

            $booking->payment?->update([
                'status' => 'success',
                'transaction_code' => $data['reference'] ?? $data['paymentLinkId'] ?? $booking->payment?->transaction_code,
                'paid_at' => now(),
            ]);
            $booking->foodOrder()->update(['status' => 'paid']);

            if ($booking->voucher_id) {
                $booking->voucher()->increment('used_count');
            }

            app(LoyaltyPointService::class)->awardForBooking($booking);

            return true;
        });
    }

    private function sendTicketEmail(Booking $booking): void
    {
        try {
            $booking->loadMissing(['user']);

            if (! $booking->user?->email) {
                return;
            }

            Mail::to($booking->user->email)->send(new BookingTicketMail($booking));
        } catch (\Throwable $exception) {
            Log::warning('Could not send booking ticket email', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
