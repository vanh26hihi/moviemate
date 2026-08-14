<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\VnpayConfig;
use App\Domain\Payments\VnpaySigner;
use App\Exceptions\PaymentInitiationException;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Models\DiscountCode;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\Payment;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use App\Services\Payments\PaymentInitiationService;
use App\Services\Payments\PaymentReturnTokenService;
use App\Services\Vnpay\VnpayPaymentUrlBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VnpayPaymentFlowTest extends VnpayPaymentTestCase
{
    public function test_removed_promotion_cannot_leak_into_the_final_provider_amount(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = FoodItem::query()->create([
            'name' => 'Promotion replacement combo',
            'price' => 55_000,
            'active' => true,
        ]);
        foreach ([['OLD20K', 20_000], ['FINAL10K', 10_000]] as [$code, $discount]) {
            DiscountCode::query()->create([
                'code' => $code,
                'name' => $code,
                'discount_type' => 'fixed',
                'discount_value' => $discount,
            ]);
        }

        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();
        $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'promotion-change@example.test',
            'food_items' => [['food_id' => $food->id, 'quantity' => 1]],
        ])->assertRedirect(route('user.bookings.review'));
        foreach ([['apply', 'OLD20K'], ['remove', 'OLD20K'], ['apply', 'FINAL10K']] as [$action, $code]) {
            $this->from(route('user.bookings.review'))->post(route('user.bookings.promotions'), [
                'action' => $action,
                'code' => $code,
            ])->assertRedirect(route('user.bookings.review'));
        }

        $response = $this->post(route('user.bookings.confirm'), ['payment_method' => 'vnpay']);
        $booking = Booking::query()->with(['discountCodeRedemptions', 'payments'])->sole();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);

        $response->assertRedirectContains('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $this->assertSame(10_000, $booking->promotion_discount_amount);
        $this->assertSame(95_000, (int) $booking->total_amount);
        $this->assertSame(95_000, $booking->payments->sole()->amount);
        $this->assertSame('9500000', $parameters['vnp_Amount']);
        $this->assertSame(['FINAL10K'], $booking->discountCodeRedemptions->pluck('code_snapshot')->all());
    }

    public function test_discounted_review_confirms_with_the_net_amount_and_redirects_to_vnpay(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = FoodItem::query()->create([
            'name' => 'Promotion payment combo',
            'price' => 55_000,
            'active' => true,
        ]);
        DiscountCode::query()->create([
            'code' => 'PAYMENT20K',
            'name' => 'Payment redirect regression',
            'discount_type' => 'fixed',
            'discount_value' => 20_000,
        ]);

        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();
        $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'promotion-payment@example.test',
            'food_items' => [['food_id' => $food->id, 'quantity' => 1]],
        ])->assertRedirect(route('user.bookings.review'));
        $this->from(route('user.bookings.review'))->post(route('user.bookings.promotions'), [
            'action' => 'apply',
            'code' => 'PAYMENT20K',
        ])->assertRedirect(route('user.bookings.review'));

        $this->get(route('user.bookings.review'))
            ->assertOk()
            ->assertViewHas('promotion', fn ($promotion): bool => $promotion->discountAmount === 20_000
                && $promotion->finalAmount === 85_000)
            ->assertSee('50.000 VNĐ')
            ->assertSee('55.000 VNĐ')
            ->assertSee('85.000 VNĐ');

        $response = $this->post(route('user.bookings.confirm'), ['payment_method' => 'vnpay']);
        $booking = Booking::query()->with(['discountCodeRedemptions', 'payments'])->sole();
        $payment = $booking->payments->sole();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);

        $response->assertRedirectContains('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $this->assertSame(50_000, $booking->seat_subtotal);
        $this->assertSame(55_000, $booking->food_subtotal);
        $this->assertSame(105_000, $booking->gross_amount);
        $this->assertSame(20_000, $booking->promotion_discount_amount);
        $this->assertSame(85_000, (int) $booking->total_amount);
        $this->assertSame(85_000, $payment->amount);
        $this->assertSame('8500000', $parameters['vnp_Amount']);
        $this->assertSame('PAYMENT20K', $booking->discountCodeRedemptions->sole()->code_snapshot);
        $this->assertSame(20_000, $booking->discountCodeRedemptions->sole()->discount_amount);
        $this->assertSame('reserved', $booking->discountCodeRedemptions->sole()->status);
        $this->assertNotSame(route('user.bookings.pending', $booking), $response->headers->get('Location'));
    }

    public function test_initiation_persists_attempt_before_building_a_valid_signed_url(): void
    {
        $booking = $this->payableBooking();
        $result = app(PaymentInitiationService::class)->initiate($booking, 'vnpay', '203.0.113.7');
        parse_str((string) parse_url($result->orderUrl, PHP_URL_QUERY), $parameters);

        $this->assertSame('vnpay', $result->payment->provider);
        $this->assertSame(50000, $result->payment->amount);
        $this->assertSame('5000000', $parameters['vnp_Amount']);
        $this->assertSame('203.0.113.7', $parameters['vnp_IpAddr']);
        $this->assertSame('2.1.0', $parameters['vnp_Version']);
        $this->assertSame('pay', $parameters['vnp_Command']);
        $this->assertSame('VND', $parameters['vnp_CurrCode']);
        $this->assertSame($result->payment->order_code, $parameters['vnp_TxnRef']);
        $this->assertMatchesRegularExpression('/^\d{14}$/', $parameters['vnp_CreateDate']);
        $this->assertMatchesRegularExpression('/^\d{14}$/', $parameters['vnp_ExpireDate']);
        $this->assertSame('VNPAYQR', $parameters['vnp_BankCode']);
        $this->assertArrayNotHasKey('vnp_SecureHashType', $parameters);
        $this->assertLessThanOrEqual(255, strlen($parameters['vnp_ReturnUrl']));
        $rawQuery = (string) parse_url($result->orderUrl, PHP_URL_QUERY);
        [$unsignedQuery] = explode('&vnp_SecureHash=', $rawQuery, 2);
        $this->assertSame(app(VnpaySigner::class)->paymentCanonical($parameters), $unsignedQuery);
        $this->assertTrue(app(VnpaySigner::class)->verifyPayment(
            $parameters,
            $parameters['vnp_SecureHash'],
            (string) config('payment.vnpay.hash_secret'),
        ));
        $this->assertNull($result->payment->payment_url);
        $this->assertNull($result->payment->order_url);
        $this->assertStringNotContainsString((string) config('payment.vnpay.hash_secret'), $result->orderUrl);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
    }

    public function test_blank_bank_code_is_omitted_and_pending_attempt_is_idempotently_reused(): void
    {
        config(['payment.vnpay.bank_code' => '']);
        $booking = $this->payableBooking();
        $first = app(PaymentInitiationService::class)->initiate($booking, 'vnpay');
        $second = app(PaymentInitiationService::class)->initiate($booking, 'vnpay');
        parse_str((string) parse_url($second->orderUrl, PHP_URL_QUERY), $parameters);

        $this->assertTrue($second->replayed);
        $this->assertSame($first->payment->id, $second->payment->id);
        $this->assertArrayNotHasKey('vnp_BankCode', $parameters);
        $this->assertSame(1, $booking->payments()->count());
    }

    public function test_expired_rejected_attempt_gets_one_atomic_replacement_with_a_new_transaction_reference(): void
    {
        config(['payment.vnpay.bank_code' => '']);
        $booking = $this->payableBooking();
        $old = $this->vnpayPayment($booking, [
            'expires_at' => now()->subMinute(),
            'provider_transaction_created_at' => now()->subMinutes(16),
        ]);
        $bookingCount = $this->app['db']->table('bookings')->count();
        $seatCount = $this->app['db']->table('booking_seats')->count();
        $orderCount = $this->app['db']->table('orders')->count();

        $result = app(PaymentInitiationService::class)->initiate($booking, 'vnpay', '203.0.113.7');

        $this->assertFalse($result->replayed);
        $this->assertNotSame($old->id, $result->payment->id);
        $this->assertNotSame($old->order_code, $result->payment->order_code);
        $this->assertSame(Payment::STATUS_EXPIRED, $old->fresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, $result->payment->status);
        $this->assertSame(2, $booking->payments()->where('provider', 'vnpay')->count());
        $this->assertSame($bookingCount, $this->app['db']->table('bookings')->count());
        $this->assertSame($seatCount, $this->app['db']->table('booking_seats')->count());
        $this->assertSame($orderCount, $this->app['db']->table('orders')->count());
    }

    public function test_successful_attempt_is_never_replaced(): void
    {
        $booking = $this->payableBooking();
        $successful = $this->vnpayPayment($booking, ['status' => Payment::STATUS_SUCCESS]);
        $booking->forceFill(['payment_status' => 'paid', 'booking_status' => 'paid'])->save();

        try {
            app(PaymentInitiationService::class)->initiate($booking, 'vnpay', '203.0.113.7');
            $this->fail('A paid booking must never create a replacement attempt.');
        } catch (PaymentInitiationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, $booking->payments()->count());
        $this->assertSame(Payment::STATUS_SUCCESS, $successful->fresh()->status);
    }

    public function test_compact_return_state_keeps_the_official_return_url_within_255_bytes(): void
    {
        $payment = $this->vnpayPayment();
        $state = app(PaymentReturnTokenService::class)->issue($payment);
        $url = app(VnpayConfig::class)->returnUrl($state);

        $this->assertStringStartsWith('v2.', $state);
        $this->assertLessThan(100, strlen($state));
        $this->assertLessThanOrEqual(255, strlen($url));
        $this->assertTrue(app(PaymentReturnTokenService::class)->verify($payment, $state));
        $this->assertFalse(app(PaymentReturnTokenService::class)->verify(
            $payment,
            substr($state, 0, -1).($state[-1] === 'A' ? 'B' : 'A'),
        ));
    }

    public function test_pay_request_normalizes_ipv4_mapped_ipv6_and_accepts_a_single_ipv6_address(): void
    {
        config(['payment.vnpay.bank_code' => '']);
        $mapped = app(PaymentInitiationService::class)->initiate(
            $this->payableBooking(),
            'vnpay',
            '::ffff:203.0.113.7',
        );
        parse_str((string) parse_url($mapped->orderUrl, PHP_URL_QUERY), $mappedParameters);
        $this->assertSame('203.0.113.7', $mappedParameters['vnp_IpAddr']);

        $ipv6 = app(PaymentInitiationService::class)->initiate(
            $this->payableBooking(),
            'vnpay',
            '2001:db8:85a3::8a2e:370:7334',
        );
        parse_str((string) parse_url($ipv6->orderUrl, PHP_URL_QUERY), $ipv6Parameters);
        $this->assertSame('2001:db8:85a3::8a2e:370:7334', $ipv6Parameters['vnp_IpAddr']);
    }

    public function test_invalid_or_oversized_return_url_is_blocked_before_redirect(): void
    {
        $payment = $this->vnpayPayment();
        $this->expectException(PaymentInitiationException::class);

        app(VnpayPaymentUrlBuilder::class)->build(
            $payment,
            'https://merchant.example.test/payments/vnpay/return?state='.str_repeat('x', 260),
            '203.0.113.7',
        );
    }

    public function test_amount_over_twelve_provider_digits_and_forwarded_ip_chain_are_rejected(): void
    {
        $oversized = $this->vnpayPayment(overrides: ['amount' => 10_000_000_001]);
        $returnUrl = app(VnpayConfig::class)->returnUrl(
            app(PaymentReturnTokenService::class)->issue($oversized),
        );
        try {
            app(VnpayPaymentUrlBuilder::class)->build($oversized, $returnUrl, '203.0.113.7');
            $this->fail('A provider amount over 12 digits must fail.');
        } catch (PaymentInitiationException) {
            $this->addToAssertionCount(1);
        }

        $payment = $this->vnpayPayment();
        $returnUrl = app(VnpayConfig::class)->returnUrl(
            app(PaymentReturnTokenService::class)->issue($payment),
        );
        foreach (['198.51.100.1, 203.0.113.7', 'unknown', 'proxy.internal'] as $ip) {
            try {
                app(VnpayPaymentUrlBuilder::class)->build($payment, $returnUrl, $ip);
                $this->fail('A non-single client IP must fail.');
            } catch (PaymentInitiationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_invalid_configuration_is_blocked_before_redirect_with_a_safe_vietnamese_message(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('user');
        $booking = $this->payableBooking(['user_id' => $user->id]);
        config(['payment.vnpay.hash_secret' => 'invalid-secret']);

        $this->actingAs($user)
            ->post(route('payments.vnpay.initiate', $booking))
            ->assertRedirect(route('user.bookings.pending', $booking))
            ->assertSessionHas(
                'warning',
                'Không thể khởi tạo thanh toán VNPAY. Vui lòng thử lại hoặc liên hệ hỗ trợ.',
            );

        $this->assertSame(0, $booking->payments()->count());
    }

    public function test_valid_ipn_is_the_only_path_that_fulfils_and_duplicate_is_idempotent(): void
    {
        $payment = $this->vnpayPayment();
        $parameters = $this->signedParameters($payment);

        $this->getJson(route('payments.vnpay.ipn', $parameters))
            ->assertOk()
            ->assertHeaderMissing('Set-Cookie')
            ->assertExactJson(['RspCode' => '00', 'Message' => 'Confirm success']);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('paid', $payment->booking->fresh()->payment_status);
        $this->assertSame(1, BookingTicketDelivery::query()->where('booking_id', $payment->booking_id)->count());

        $this->getJson(route('payments.vnpay.ipn', $parameters))
            ->assertOk()->assertExactJson(['RspCode' => '02', 'Message' => 'Order already confirmed']);
        $this->assertSame(1, BookingTicketDelivery::query()->where('booking_id', $payment->booking_id)->count());
    }

    public function test_unsigned_ipn_probe_is_session_independent_and_never_mutates_business_data(): void
    {
        $payment = $this->vnpayPayment();
        $before = [
            'bookings' => $this->app['db']->table('bookings')->count(),
            'booking_seats' => $this->app['db']->table('booking_seats')->count(),
            'orders' => $this->app['db']->table('orders')->count(),
            'order_items' => $this->app['db']->table('order_items')->count(),
            'payments' => $this->app['db']->table('payments')->count(),
        ];

        $this->getJson(route('payments.vnpay.ipn'))
            ->assertOk()
            ->assertHeaderMissing('Set-Cookie')
            ->assertExactJson(['RspCode' => '97', 'Message' => 'Invalid signature']);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame($before, [
            'bookings' => $this->app['db']->table('bookings')->count(),
            'booking_seats' => $this->app['db']->table('booking_seats')->count(),
            'orders' => $this->app['db']->table('orders')->count(),
            'order_items' => $this->app['db']->table('order_items')->count(),
            'payments' => $this->app['db']->table('payments')->count(),
        ]);
    }

    public function test_invalid_signature_and_amount_mismatch_never_pay_or_issue_ticket(): void
    {
        Log::spy();
        $payment = $this->vnpayPayment();
        $invalid = $this->signedParameters($payment);
        $invalid['vnp_SecureHash'] = str_repeat('0', 128);
        $this->getJson(route('payments.vnpay.ipn', $invalid))->assertJsonPath('RspCode', '97');
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);

        $mismatch = $this->signedParameters($payment, ['vnp_Amount' => '999900']);
        $this->getJson(route('payments.vnpay.ipn', $mismatch))->assertJsonPath('RspCode', '04');
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
        $this->assertDatabaseMissing('booking_ticket_deliveries', ['booking_id' => $payment->booking_id]);
        $failedRetry = $this->signedParameters($payment, ['vnp_TransactionStatus' => '02']);
        $this->getJson(route('payments.vnpay.ipn', $failedRetry))->assertJsonPath('RspCode', '00');
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
    }

    public function test_unknown_reference_and_merchant_mismatch_are_rejected_without_mutation(): void
    {
        $payment = $this->vnpayPayment();
        $unknown = $this->signedParameters($payment, ['vnp_TxnRef' => 'MMUNKNOWNREFERENCE']);
        $this->getJson(route('payments.vnpay.ipn', $unknown))->assertJsonPath('RspCode', '01');

        $merchant = $this->signedParameters($payment, ['vnp_TmnCode' => 'OTHER123']);
        $this->getJson(route('payments.vnpay.ipn', $merchant))->assertJsonPath('RspCode', '99');
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_partial_success_codes_never_fulfil(): void
    {
        $responseOnly = $this->vnpayPayment();
        $this->getJson(route('payments.vnpay.ipn', $this->signedParameters(
            $responseOnly,
            ['vnp_TransactionStatus' => '02'],
        )))->assertJsonPath('RspCode', '00');
        $this->assertSame(Payment::STATUS_FAILED, $responseOnly->fresh()->status);
        $this->assertSame('cancelled', $responseOnly->booking->fresh()->booking_status);
        $this->assertSame(0, $responseOnly->booking->bookingSeats()->whereNotNull('active_lock_key')->count());

        $statusOnly = $this->vnpayPayment();
        $this->getJson(route('payments.vnpay.ipn', $this->signedParameters(
            $statusOnly,
            ['vnp_ResponseCode' => '24'],
        )))->assertJsonPath('RspCode', '00');
        $this->assertSame(Payment::STATUS_REVIEW, $statusOnly->fresh()->status);
        $this->assertSame(0, BookingTicketDelivery::query()->count());
    }

    public function test_signed_code_24_cancels_immediately_without_query_and_releases_for_another_customer(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('user');
        $payment = $this->vnpayPayment($this->payableBooking(['user_id' => $user->id]));
        $this->actingAs($user);
        $seatId = $payment->booking->bookingSeats()->sole()->seat_id;
        Order::query()->create([
            'booking_id' => $payment->booking_id,
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'pickup_cinema_id' => $payment->booking->cinema_id,
            'subtotal' => 10000,
            'total_amount' => 10000,
            'status' => 'pending',
        ]);
        Http::fake();
        $state = app(PaymentReturnTokenService::class)->issue($payment);

        $response = $this->get(route('payments.vnpay.return', $this->signedParameters($payment, [
            'vnp_ResponseCode' => '24',
        ]) + ['state' => $state]));

        $response->assertRedirect(route('payments.vnpay.return', ['ref' => $payment->order_code]));
        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Thanh toán đã được hủy')
            ->assertSee('Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.')
            ->assertDontSee('Giao dịch cần được hỗ trợ')
            ->assertDontSee('data-countdown=', false);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame('vnpay_customer_cancelled', $payment->fresh()->failure_reason);
        $this->assertSame('cancelled', $payment->booking->fresh()->booking_status);
        $this->assertSame(0, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertDatabaseHas('orders', ['booking_id' => $payment->booking_id, 'status' => 'cancelled']);
        Http::assertNothingSent();

        $this->get(route('user.bookings.success', $payment->booking))
            ->assertOk()
            ->assertSee('Thanh toán đã được hủy')
            ->assertSee('Đặt vé lại')
            ->assertDontSee('Thanh toán tiếp')
            ->assertDontSee('Hủy đơn');

        $this->withoutVite();
        $this->get(route('user.bookings.history'))
            ->assertOk()
            ->assertSee('Đã hủy')
            ->assertDontSee('Thanh toán tiếp')
            ->assertDontSee('Hủy đơn');
        $this->get(route('user.bookings.selectSeat', $payment->booking->showtime_id))
            ->assertOk()
            ->assertSee('Ghế A1, loại Thường, còn trống');

        $replacement = app(BookingCheckoutService::class)->createPendingBooking(
            $payment->booking->showtime_id,
            [$seatId],
            null,
            'next-customer@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
        )->booking;
        $this->assertSame(1, $replacement->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_signed_code_24_does_not_depend_on_querydr_availability(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake(['*' => fn () => Http::failedConnection('timeout')]);
        $state = app(PaymentReturnTokenService::class)->issue($payment);

        $response = $this->get(route('payments.vnpay.return', $this->signedParameters($payment, [
            'vnp_ResponseCode' => '24',
            'vnp_TransactionStatus' => '02',
        ]) + ['state' => $state]));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Thanh toán đã được hủy')
            ->assertDontSee('Đang xác minh việc hủy thanh toán')
            ->assertDontSee('data-countdown=', false);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame('cancelled', $payment->booking->fresh()->booking_status);
        $this->assertSame(0, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        Http::assertNothingSent();
    }

    public function test_late_signed_code_24_cannot_downgrade_authoritatively_paid_booking(): void
    {
        $payment = $this->vnpayPayment(overrides: [
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
        ]);
        $payment->booking->forceFill([
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
        ])->save();
        Http::fake();
        $state = app(PaymentReturnTokenService::class)->issue($payment);

        $response = $this->get(route('payments.vnpay.return', $this->signedParameters($payment, [
            'vnp_ResponseCode' => '24',
            'vnp_TransactionStatus' => '02',
        ]) + ['state' => $state]))->assertRedirect();

        $this->followRedirects($response)->assertSee('Đặt vé thành công');
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('paid', $payment->booking->fresh()->booking_status);
        $this->assertSame(1, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        Http::assertNothingSent();
    }

    public function test_signed_code_24_amount_mismatch_is_rejected_without_query_or_release(): void
    {
        $payment = $this->vnpayPayment();
        Http::fake();
        $state = app(PaymentReturnTokenService::class)->issue($payment);
        $parameters = $this->signedParameters($payment, [
            'vnp_Amount' => (string) (($payment->amount + 1) * 100),
            'vnp_ResponseCode' => '24',
            'vnp_TransactionStatus' => '02',
        ]) + ['state' => $state];

        $this->get(route('payments.vnpay.return', $parameters))->assertNotFound();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('pending_payment', $payment->booking->fresh()->booking_status);
        $this->assertSame(1, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        Http::assertNothingSent();
    }

    public function test_duplicate_signed_return_and_ipn_log_once_and_late_success_never_reclaims_rebooked_seat(): void
    {
        $payment = $this->vnpayPayment();
        $cancel = $this->signedParameters($payment, [
            'vnp_ResponseCode' => '24',
            'vnp_TransactionStatus' => '02',
        ]);
        $state = app(PaymentReturnTokenService::class)->issue($payment);

        $this->get(route('payments.vnpay.return', $cancel + ['state' => $state]))->assertRedirect();
        $this->get(route('payments.vnpay.return', $cancel + ['state' => $state]))->assertRedirect();
        $this->getJson(route('payments.vnpay.ipn', $cancel))->assertJsonPath('RspCode', '00');

        $activity = ActivityLog::query()
            ->where('action', 'booking.payment_cancelled')
            ->where('subject_id', $payment->booking_id)
            ->sole();
        $this->assertSame($payment->id, $activity->context['payment_id']);
        $this->assertSame('vnpay', $activity->context['provider']);
        $this->assertSame('customer_cancelled', $activity->context['result']);
        $this->assertArrayNotHasKey('vnp_SecureHash', $activity->context);

        $seatId = $payment->booking->bookingSeats()->sole()->seat_id;
        $replacement = app(BookingCheckoutService::class)->createPendingBooking(
            $payment->booking->showtime_id,
            [$seatId],
            null,
            'replacement-after-vnpay-cancel@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
        )->booking;

        $this->getJson(route('payments.vnpay.ipn', $this->signedParameters($payment)))
            ->assertJsonPath('RspCode', '99');
        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('cancelled', $payment->booking->fresh()->booking_status);
        $this->assertSame(0, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertSame(1, $replacement->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertDatabaseMissing('booking_ticket_deliveries', ['booking_id' => $payment->booking_id]);
    }

    public function test_late_or_seat_lost_success_moves_to_review_without_ticket(): void
    {
        $late = $this->vnpayPayment();
        $late->booking->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->getJson(route('payments.vnpay.ipn', $this->signedParameters($late)))
            ->assertJsonPath('RspCode', '99');
        $this->assertSame(Payment::STATUS_REVIEW, $late->fresh()->status);

        $seatLost = $this->vnpayPayment();
        $seatLost->booking->bookingSeats()->update(['active_lock_key' => null]);
        $this->getJson(route('payments.vnpay.ipn', $this->signedParameters($seatLost)))
            ->assertJsonPath('RspCode', '99');
        $this->assertSame(Payment::STATUS_REVIEW, $seatLost->fresh()->status);
        $this->assertSame(0, BookingTicketDelivery::query()->count());
    }

    public function test_return_signature_only_grants_read_access_and_cleans_provider_parameters(): void
    {
        $payment = $this->vnpayPayment();
        $state = app(PaymentReturnTokenService::class)->issue($payment);
        $parameters = $this->signedParameters($payment) + ['state' => $state];

        $response = $this->get(route('payments.vnpay.return', $parameters));
        $response->assertRedirect(route('payments.vnpay.return', ['ref' => $payment->order_code]));
        $this->followRedirects($response)->assertOk()->assertSee('VNPAY');
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_forwarded_https_return_redirects_to_a_clean_https_url_without_fulfilling(): void
    {
        config([
            'trustedproxy.proxies' => ['127.0.0.1'],
            'trustedproxy.hosts' => ['merchant.example.test'],
        ]);
        $payment = $this->vnpayPayment();
        $state = app(PaymentReturnTokenService::class)->issue($payment);
        $parameters = $this->signedParameters($payment) + ['state' => $state];
        $path = route('payments.vnpay.return', $parameters, false);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-For' => '198.51.100.42',
                'X-Forwarded-Host' => 'merchant.example.test',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])->get('http://upstream.internal'.$path);

        $response->assertRedirect('https://merchant.example.test/payments/vnpay/return?ref='.$payment->order_code);
        $this->assertStringNotContainsString('vnp_SecureHash', (string) $response->headers->get('Location'));
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('unpaid', $payment->booking->fresh()->payment_status);
    }

    public function test_forged_return_is_rejected_without_database_mutation(): void
    {
        $payment = $this->vnpayPayment();
        $parameters = $this->signedParameters($payment, ['vnp_ResponseCode' => '24']);
        $parameters['vnp_SecureHash'] = str_repeat('0', 128);

        $this->get(route('payments.vnpay.return', $parameters))->assertNotFound();
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(1, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_signed_code_24_with_unknown_reference_cannot_release_another_booking(): void
    {
        $payment = $this->vnpayPayment();
        $state = app(PaymentReturnTokenService::class)->issue($payment);
        $parameters = $this->signedParameters($payment, [
            'vnp_ResponseCode' => '24',
            'vnp_TxnRef' => 'MMUNKNOWNREFERENCE',
        ]);

        $this->get(route('payments.vnpay.return', $parameters + ['state' => $state]))->assertNotFound();
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('pending_payment', $payment->booking->fresh()->booking_status);
        $this->assertSame(1, $payment->booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_guest_return_capability_is_scoped_to_one_payment(): void
    {
        $allowed = $this->vnpayPayment();
        $other = $this->vnpayPayment();
        $state = app(PaymentReturnTokenService::class)->issue($allowed);
        $this->get(route('payments.vnpay.return', $this->signedParameters($allowed) + ['state' => $state]))
            ->assertRedirect();

        $this->get(route('payments.vnpay.return', ['ref' => $other->order_code]))->assertNotFound();
    }
}
