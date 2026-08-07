<?php

namespace Tests\Feature\Payments;

use App\Models\ActivityLog;
use App\Models\BookingSeat;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\Payment;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use Illuminate\Support\Facades\DB;

class PaymentResumeHotfixTest extends VnpayPaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seedRbac();
        config([
            'services.payos.client_id' => 'payos-test-client-id',
            'services.payos.api_key' => 'payos-test-api-key',
            'services.payos.checksum_key' => 'payos-test-checksum-key-only',
            'services.payos.base_url' => 'https://api-merchant.payos.vn',
        ]);
    }

    public function test_abandoned_vnpay_attempt_is_visible_and_resumes_the_same_reference(): void
    {
        $owner = $this->userWithRole('user');
        $booking = $this->payableBooking(['user_id' => $owner->id]);
        $payment = $this->vnpayPayment($booking);
        $seatIds = $booking->bookingSeats()->pluck('seat_id')->all();
        $activityCount = ActivityLog::query()->count();

        $this->actingAs($owner)->get(route('user.bookings.history'))
            ->assertOk()
            ->assertSee('Chờ thanh toán')
            ->assertSee('Tiếp tục thanh toán')
            ->assertSee('Hủy đơn')
            ->assertSee('action="'.route('payments.resume', $booking).'"', false)
            ->assertSee('action="'.route('user.bookings.cancel', $booking).'"', false);

        $response = $this->actingAs($owner)->post(route('payments.resume', $booking), [
            'provider' => 'zalopay',
        ])->assertRedirect();

        $url = (string) $response->headers->get('Location');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);

        $this->assertStringStartsWith('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?', $url);
        $this->assertSame($payment->order_code, $parameters['vnp_TxnRef'] ?? null);
        $this->assertSame(1, Payment::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
        $this->assertSame($seatIds, $booking->bookingSeats()->pluck('seat_id')->all());
        $this->assertSame(1, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertSame($activityCount, ActivityLog::query()->count());
    }

    public function test_customer_can_cancel_abandoned_vnpay_and_release_food_and_seat_immediately(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $food = FoodItem::query()->create([
            'name' => 'Combo resume cancellation',
            'price' => 65_000,
            'active' => true,
        ]);
        $booking = app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            [$scenario['seats'][0]->id],
            $owner->id,
            $owner->email,
            app(BookingTokenService::class)->issueCheckoutToken(),
            [['food_id' => $food->id, 'quantity' => 1]],
        )->booking;
        $payment = $this->vnpayPayment($booking);
        $order = Order::query()->where('booking_id', $booking->id)->sole();

        $cancelQueries = $this->measure(fn () => $this->actingAs($owner)
            ->delete(route('user.bookings.cancel', $booking))
            ->assertRedirect(route('user.bookings.history'))
            ->assertSessionHas('success', 'Đã hủy đơn đặt vé và giải phóng các ghế đang giữ.'));

        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame('customer_cancelled_pending_payment', $payment->fresh()->failure_reason);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'booking.cancelled')->count());
        $this->assertLessThanOrEqual(20, $cancelQueries);

        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PAYMENT_CANCEL_QUERY_COUNTS='.json_encode(
                compact('cancelQueries'),
                JSON_THROW_ON_ERROR,
            ).PHP_EOL);
        }

        $this->actingAs($owner)->get(route('user.bookings.history'))
            ->assertOk()
            ->assertSee('Đã hủy')
            ->assertDontSee('action="'.route('payments.resume', $booking).'"', false)
            ->assertDontSee('action="'.route('user.bookings.cancel', $booking).'"', false);

        $replacement = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $this->assertNotSame($booking->id, $replacement->id);
        $this->assertSame(BookingSeat::ACTIVE_LOCK_KEY, $replacement->bookingSeats()->sole()->active_lock_key);
    }

    public function test_pending_payos_attempt_reuses_the_same_link_and_provider_identity(): void
    {
        $owner = $this->userWithRole('user');
        $booking = $this->payableBooking(['user_id' => $owner->id]);
        $checkoutUrl = 'https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18';
        $payment = Payment::createForProvider('payos', [
            'booking_id' => $booking->id,
            'payment_method' => 'payos',
            'order_code' => '88112233',
            'amount' => 50_000,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
            'transaction_code' => '124c33293c43417ab7879e14c8d9eb18',
            'transaction_status' => 'PENDING',
            'payment_url' => $checkoutUrl,
            'expires_at' => now()->addMinutes(10),
            'reconcile_until' => now()->addDay(),
        ]);

        $this->actingAs($owner)->get(route('user.bookings.history'))
            ->assertOk()
            ->assertSee('action="'.route('payments.payos.cancel-attempt', $booking).'"', false)
            ->assertSee('Tiếp tục thanh toán')
            ->assertSee('Hủy đơn');

        $resumeQueries = $this->measure(fn () => $this->actingAs($owner)->post(route('payments.resume', $booking), [
            'provider' => 'vnpay',
        ])->assertRedirect($checkoutUrl));

        $fresh = $payment->fresh();
        $this->assertSame(1, Payment::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('88112233', $fresh->order_code);
        $this->assertSame('124c33293c43417ab7879e14c8d9eb18', $fresh->transaction_code);
        $this->assertSame($checkoutUrl, $fresh->payment_url);
        $this->assertSame(Payment::STATUS_PENDING, $fresh->status);
        $this->assertLessThanOrEqual(12, $resumeQueries);

        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PAYOS_RESUME_QUERY_COUNTS='.json_encode(
                compact('resumeQueries'),
                JSON_THROW_ON_ERROR,
            ).PHP_EOL);
        }
    }

    public function test_pending_zalopay_attempt_reuses_the_same_checkout_url_and_transaction(): void
    {
        $owner = $this->userWithRole('user');
        $booking = $this->payableBooking(['user_id' => $owner->id]);
        $checkoutUrl = 'https://zalopay.example.test/existing-checkout';
        $payment = $this->pendingPayment($booking, [
            'order_url' => $checkoutUrl,
            'zp_trans_token' => 'existing-zalopay-token',
        ]);
        $appTransId = $payment->app_trans_id;

        $this->actingAs($owner)->post(route('payments.resume', $booking), [
            'provider' => 'payos',
        ])->assertRedirect($checkoutUrl);

        $fresh = $payment->fresh();
        $this->assertSame(1, Payment::query()->where('booking_id', $booking->id)->count());
        $this->assertSame($appTransId, $fresh->app_trans_id);
        $this->assertSame($checkoutUrl, $fresh->order_url);
        $this->assertSame(Payment::STATUS_PENDING, $fresh->status);
    }

    public function test_processing_unresolved_and_review_attempts_have_no_customer_actions_or_direct_resume(): void
    {
        $owner = $this->userWithRole('user');
        $expectations = [
            Payment::STATUS_PROCESSING => 'Đang xác minh',
            Payment::STATUS_UNRESOLVED => 'Đang xác minh',
            Payment::STATUS_REVIEW => 'Cần hỗ trợ',
        ];

        foreach ($expectations as $status => $badge) {
            $booking = $this->payableBooking(['user_id' => $owner->id]);
            $payment = $this->vnpayPayment($booking, ['status' => $status]);

            $this->actingAs($owner)->get(route('user.bookings.history'))
                ->assertOk()
                ->assertSee($badge)
                ->assertDontSee('action="'.route('payments.resume', $booking).'"', false)
                ->assertDontSee('action="'.route('user.bookings.cancel', $booking).'"', false);

            $response = $this->actingAs($owner)->post(route('payments.resume', $booking));
            $status === Payment::STATUS_REVIEW
                ? $response->assertRedirect(route('user.bookings.payment-review', $booking))
                : $response->assertRedirect(route('user.bookings.pending', $booking));

            $response->assertSessionHas('warning', $status === Payment::STATUS_REVIEW
                ? 'Giao dịch cần được hỗ trợ. MovieMate sẽ cập nhật khi có kết quả chính thức.'
                : 'MovieMate đang xác minh kết quả thanh toán. Vui lòng không tạo thêm giao dịch cho đơn này.');
            $this->assertSame(1, Payment::query()->where('booking_id', $booking->id)->count());
            $this->assertSame($status, $payment->fresh()->status);
            $this->assertSame(BookingSeat::ACTIVE_LOCK_KEY, $booking->bookingSeats()->sole()->active_lock_key);
        }
    }

    public function test_expired_cancelled_paid_and_other_customers_cannot_resume(): void
    {
        $owner = $this->userWithRole('user');
        $other = $this->userWithRole('user');

        $owned = $this->payableBooking(['user_id' => $owner->id]);
        $this->vnpayPayment($owned);
        $this->actingAs($other)->post(route('payments.resume', $owned))->assertForbidden();

        $expired = $this->payableBooking([
            'user_id' => $owner->id,
            'expires_at' => now()->subMinute(),
        ]);
        $this->vnpayPayment($expired, ['expires_at' => now()->subMinute()]);
        $this->actingAs($owner)->post(route('payments.resume', $expired))
            ->assertRedirect(route('user.bookings.expired', $expired))
            ->assertSessionHas('warning', 'Thời gian giữ ghế đã hết. Vui lòng chọn ghế lại.');

        $cancelled = $this->payableBooking(['user_id' => $owner->id]);
        $this->vnpayPayment($cancelled, [
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => 'vnpay_customer_cancelled',
            'failed_at' => now(),
        ]);
        $cancelled->forceFill(['booking_status' => 'cancelled'])->save();
        $this->actingAs($owner)->post(route('payments.resume', $cancelled))
            ->assertRedirect(route('user.bookings.success', $cancelled));

        $paid = $this->payableBooking(['user_id' => $owner->id]);
        $this->vnpayPayment($paid, [
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
        ]);
        $paid->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid'])->save();
        $this->actingAs($owner)->post(route('payments.resume', $paid))
            ->assertRedirect(route('user.bookings.success', $paid));

        $this->assertSame(1, Payment::query()->where('booking_id', $owned->id)->count());
        $this->assertSame(1, Payment::query()->where('booking_id', $expired->id)->count());
        $this->assertSame(1, Payment::query()->where('booking_id', $cancelled->id)->count());
        $this->assertSame(1, Payment::query()->where('booking_id', $paid->id)->count());
    }

    public function test_cancel_winner_cannot_be_revived_by_resume_and_duplicate_cancel_is_idempotent(): void
    {
        $owner = $this->userWithRole('user');
        $booking = $this->payableBooking(['user_id' => $owner->id]);
        $payment = $this->vnpayPayment($booking);

        $this->actingAs($owner)->delete(route('user.bookings.cancel', $booking))
            ->assertSessionHas('success');
        $this->actingAs($owner)->post(route('payments.resume', $booking))
            ->assertRedirect(route('user.bookings.success', $booking));
        $this->actingAs($owner)->delete(route('user.bookings.cancel', $booking))
            ->assertSessionHas('warning', 'Đơn đặt vé đã được hủy trước đó.');

        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame(0, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertSame(1, Payment::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'booking.cancelled')->count());
    }

    public function test_provider_success_winner_prevents_customer_cancel_and_keeps_inventory_protected(): void
    {
        $owner = $this->userWithRole('user');
        $booking = $this->payableBooking(['user_id' => $owner->id]);
        $payment = $this->vnpayPayment($booking, [
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
            'transaction_id' => '9988776655',
        ]);
        $booking->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid'])->save();

        $this->actingAs($owner)->delete(route('user.bookings.cancel', $booking))
            ->assertSessionHas('warning', 'Đơn đặt vé này không thể hủy ở trạng thái hiện tại.');

        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame(BookingSeat::ACTIVE_LOCK_KEY, $booking->bookingSeats()->sole()->active_lock_key);
        $this->assertSame(0, ActivityLog::query()->where('action', 'booking.cancelled')->count());
    }

    public function test_repeated_resume_posts_are_idempotent_and_queries_stay_bounded(): void
    {
        $owner = $this->userWithRole('user');
        $booking = $this->payableBooking(['user_id' => $owner->id]);
        $payment = $this->vnpayPayment($booking);

        $first = $this->measure(fn () => $this->actingAs($owner)
            ->post(route('payments.resume', $booking))
            ->assertRedirect());
        $second = $this->measure(fn () => $this->actingAs($owner)
            ->post(route('payments.resume', $booking))
            ->assertRedirect());
        $history = $this->measure(fn () => $this->actingAs($owner)
            ->get(route('user.bookings.history'))
            ->assertOk());

        $this->assertSame(1, Payment::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertLessThanOrEqual(18, $first);
        $this->assertLessThanOrEqual(18, $second);
        $this->assertLessThanOrEqual(18, $history);

        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PAYMENT_RESUME_QUERY_COUNTS='.json_encode(
                compact('history', 'first', 'second'),
                JSON_THROW_ON_ERROR,
            ).PHP_EOL);
        }
    }

    /** @param callable():mixed $callback */
    private function measure(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $callback();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
