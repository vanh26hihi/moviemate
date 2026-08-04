<?php

namespace Tests\Feature\Checkout;

use App\Models\Booking;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\Payment;
use App\Services\BookingCheckoutService;
use App\Services\BookingExpirationService;
use App\Services\BookingFoodService;
use App\Services\BookingTokenService;
use App\Services\CinemaContext;
use App\Services\UnifiedBookingCheckoutService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Feature\Payments\PaymentTestCase;

class UnifiedBookingCheckoutFlowTest extends PaymentTestCase
{
    public function test_guest_seat_and_food_flow_uses_server_totals_and_redirects_to_zalopay(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = $this->food('Combo verified', 35_000);
        Http::fake(['*' => Http::response($this->successfulCreate(), 200)]);

        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))
            ->assertOk()
            ->assertViewIs('user.bookings.food')
            ->assertSee('Ghế '.$scenario['seats'][0]->seat_code)
            ->assertSee('Bỏ qua đồ ăn')
            ->assertDontSee('checkout_token', false);

        $this->post(route('user.bookings.food.store'), [
            'customer_email' => ' Guest@Example.Test ',
            'food_items' => [[
                'food_id' => $food->id,
                'quantity' => 2,
                'price' => 1,
                'line_total' => 1,
            ]],
        ])->assertRedirect(route('user.bookings.review'));

        $this->get(route('user.bookings.review'))
            ->assertOk()
            ->assertViewIs('user.bookings.review')
            ->assertSee('50.000 VND')
            ->assertSee('70.000 VND')
            ->assertSee('120.000 VND')
            ->assertDontSee('name="total_amount"', false);

        $this->post(route('user.bookings.confirm'))
            ->assertRedirect('https://zalopay.example.test/pay');

        $booking = Booking::query()->with(['bookingSeats', 'payments'])->sole();
        $order = Order::query()->with('items')->sole();
        $payment = $booking->payments->sole();

        $this->assertSame('guest@example.test', $booking->customer_email);
        $this->assertSame('pending_payment', $booking->booking_status);
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertSame(50_000, $booking->seat_subtotal);
        $this->assertSame(70_000, $booking->food_subtotal);
        $this->assertSame(120_000, (int) $booking->total_amount);
        $this->assertSame('VND', $booking->currency);
        $this->assertSame(120_000, $payment->amount);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('pending', $order->status);
        $this->assertSame($booking->id, $order->booking_id);
        $this->assertSame(app(CinemaContext::class)->id(), $order->pickup_cinema_id);
        $this->assertSame('Combo verified', $order->items->sole()->snapshot_name);
        $this->assertSame(35_000, $order->items->sole()->unit_price);
        $this->assertSame(70_000, $order->items->sole()->line_total);
        $this->assertSame(1, $booking->bookingSeats->count());
        $this->assertNotNull($booking->bookingSeats->sole()->active_lock_key);

        Http::assertSent(fn (Request $request): bool => $request['amount'] === 120_000);

        $this->get(route('user.bookings.pending', $booking))
            ->assertOk()
            ->assertSee('Đang chờ xác minh thanh toán');
    }

    public function test_seat_only_skip_creates_no_empty_order_and_one_payment_attempt(): void
    {
        $scenario = $this->bookingScenario(false);
        Http::fake(['*' => Http::response($this->successfulCreate(), 200)]);

        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();
        $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'seat-only@example.test',
            'skip_food' => '1',
            'food_items' => [['food_id' => 999999, 'quantity' => 0]],
        ])->assertRedirect(route('user.bookings.review'));
        $this->post(route('user.bookings.confirm'))
            ->assertRedirect('https://zalopay.example.test/pay');

        $booking = Booking::query()->sole();
        $this->assertSame(0, $booking->food_subtotal);
        $this->assertSame(50_000, (int) $booking->total_amount);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'amount' => 50_000,
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    public function test_verified_callback_pays_booking_and_unified_food_order_only_once(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = $this->food('Paid combo', 25_000);
        Http::fake(['*' => Http::response($this->successfulCreate(), 200)]);

        $draft = [
            'showtime_id' => $scenario['showtime']->id,
            'seat_ids' => [$scenario['seats'][0]->id],
            'customer_email' => 'verified@example.test',
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
            'food_items' => [['food_id' => $food->id, 'quantity' => 1]],
        ];
        $result = app(UnifiedBookingCheckoutService::class)->confirm($draft, null);
        $payment = $result->payment;

        $callback = $this->callbackBody($payment, ['zp_trans_id' => 987654321]);
        $this->postJson(route('payments.zalopay.callback'), $callback)
            ->assertOk()
            ->assertJsonPath('return_code', 1);
        $this->postJson(route('payments.zalopay.callback'), $callback)
            ->assertOk()
            ->assertJsonPath('return_code', 1);

        $this->assertDatabaseHas('bookings', [
            'id' => $result->checkout->booking->id,
            'booking_status' => 'paid',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_SUCCESS,
            'zp_trans_id' => '987654321',
        ]);
        $this->assertDatabaseHas('orders', [
            'booking_id' => $result->checkout->booking->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_timeout_reconciles_same_unknown_attempt_instead_of_creating_another(): void
    {
        $scenario = $this->bookingScenario(false);
        $draft = [
            'showtime_id' => $scenario['showtime']->id,
            'seat_ids' => [$scenario['seats'][0]->id],
            'customer_email' => 'timeout@example.test',
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
            'food_items' => [],
        ];
        Http::fake(['*' => Http::failedConnection('timeout')]);

        $first = app(UnifiedBookingCheckoutService::class)->confirm($draft, null);
        $this->assertTrue($first->paymentPendingReview);
        $this->assertSame(Payment::STATUS_PENDING, $first->payment->status);
        $this->assertSame('create_transport_unknown', $first->payment->failure_reason);

        Http::fake(['*' => Http::response([
            'return_code' => 3,
            'return_message' => 'Pending',
        ], 200)]);
        $second = app(UnifiedBookingCheckoutService::class)->confirm($draft, null);

        $this->assertTrue($second->checkout->replayed);
        $this->assertTrue($second->paymentPendingReview);
        $this->assertSame($first->payment->id, $second->payment->id);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_confirm_rejects_forged_authoritative_fields_before_persistence(): void
    {
        $scenario = $this->bookingScenario(false);
        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();
        $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'secure@example.test',
            'skip_food' => 1,
        ])->assertRedirect(route('user.bookings.review'));

        $this->post(route('user.bookings.confirm'), [
            'pickup_cinema_id' => 999999,
            'total_amount' => 1,
        ])->assertSessionHasErrors(['pickup_cinema_id', 'total_amount']);

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_seats', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_draft_is_bound_to_the_actor_and_contains_no_bearer_url(): void
    {
        $this->seedRbac();
        $scenario = $this->bookingScenario(false);
        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();

        $user = $this->userWithRole('user');
        $this->actingAs($user)
            ->get(route('user.bookings.food'))
            ->assertConflict()
            ->assertJsonMissing(['guest_token']);

        $this->assertStringNotContainsString('token', route('user.bookings.food'));
        $this->assertStringNotContainsString('token', route('user.bookings.review'));
        $this->assertStringNotContainsString('token', route('user.bookings.confirm'));
    }

    public function test_missing_zalopay_transaction_id_moves_attempt_to_review_without_paying_order(): void
    {
        $booking = $this->payableBooking();
        $food = $this->food('Review combo', 20_000);
        $booking->forceFill(['food_subtotal' => 20_000, 'total_amount' => 70_000])->save();
        Order::query()->create([
            'booking_id' => $booking->id,
            'customer_name' => '',
            'customer_email' => $booking->customer_email,
            'pickup_cinema_id' => app(CinemaContext::class)->id(),
            'subtotal' => 20_000,
            'total_amount' => 20_000,
            'status' => 'pending',
        ]);
        $payment = $this->pendingPayment($booking, ['amount' => 70_000]);
        $body = $this->callbackBody($payment, ['zp_trans_id' => null]);

        $this->postJson(route('payments.zalopay.callback'), $body)
            ->assertOk()
            ->assertJsonPath('return_code', 2);

        $this->assertSame(Payment::STATUS_REVIEW, $payment->fresh()->status);
        $this->assertSame('missing_zp_trans_id', $payment->fresh()->failure_reason);
        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
        $this->assertDatabaseHas('orders', ['booking_id' => $booking->id, 'status' => 'pending']);
        $this->assertTrue($food->exists);
    }

    public function test_booking_aggregate_rolls_back_when_food_persistence_fails(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = $this->food('Rollback combo', 20_000);
        $realFoodService = app(BookingFoodService::class);
        $breakdown = $realFoodService->calculate([['food_id' => $food->id, 'quantity' => 1]]);
        $failingFoodService = \Mockery::mock(BookingFoodService::class);
        $failingFoodService->shouldReceive('calculate')->once()->andReturn($breakdown);
        $failingFoodService->shouldReceive('persist')->once()->andThrow(new RuntimeException('forced persistence failure'));
        $this->app->instance(BookingFoodService::class, $failingFoodService);
        $this->app->forgetInstance(BookingCheckoutService::class);

        try {
            app(BookingCheckoutService::class)->createPendingBooking(
                $scenario['showtime']->id,
                [$scenario['seats'][0]->id],
                null,
                'rollback@example.test',
                app(BookingTokenService::class)->issueCheckoutToken(),
                [['food_id' => $food->id, 'quantity' => 1]],
            );
            $this->fail('The forced persistence failure should escape the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced persistence failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_seats', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_late_verified_payment_keeps_booking_and_food_expired_and_moves_payment_to_review(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = $this->food('Late combo', 30_000);
        Http::fake(['*' => Http::response($this->successfulCreate(), 200)]);
        $draft = [
            'showtime_id' => $scenario['showtime']->id,
            'seat_ids' => [$scenario['seats'][0]->id],
            'customer_email' => 'late@example.test',
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
            'food_items' => [['food_id' => $food->id, 'quantity' => 1]],
        ];
        $result = app(UnifiedBookingCheckoutService::class)->confirm($draft, null);
        $booking = $result->checkout->booking;
        $booking->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->assertTrue(app(BookingExpirationService::class)->expire($booking->id));
        $this->postJson(
            route('payments.zalopay.callback'),
            $this->callbackBody($result->payment, ['zp_trans_id' => 123456789]),
        )->assertJsonPath('return_code', 2);

        $this->assertSame('expired', $booking->fresh()->booking_status);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_REVIEW, $result->payment->fresh()->status);
        $this->assertSame('late_payment_after_expiration', $result->payment->fresh()->failure_reason);
        $this->assertDatabaseHas('orders', ['booking_id' => $booking->id, 'status' => 'expired']);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_amount_mismatch_keeps_booking_and_food_pending_without_ticket_dispatch(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = $this->food('Mismatch combo', 15_000);
        Http::fake(['*' => Http::response($this->successfulCreate(), 200)]);
        $result = app(UnifiedBookingCheckoutService::class)->confirm([
            'showtime_id' => $scenario['showtime']->id,
            'seat_ids' => [$scenario['seats'][0]->id],
            'customer_email' => 'mismatch@example.test',
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
            'food_items' => [['food_id' => $food->id, 'quantity' => 1]],
        ], null);

        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($result->payment, [
            'amount' => $result->payment->amount + 1,
        ]))->assertJsonPath('return_code', 2);

        $this->assertSame(Payment::STATUS_REVIEW, $result->payment->fresh()->status);
        $this->assertSame('amount_mismatch', $result->payment->fresh()->failure_reason);
        $this->assertSame('pending_payment', $result->checkout->booking->fresh()->booking_status);
        $this->assertSame('unpaid', $result->checkout->booking->fresh()->payment_status);
        $this->assertDatabaseHas('orders', [
            'booking_id' => $result->checkout->booking->id,
            'status' => 'pending',
        ]);
        Queue::assertNothingPushed();
    }

    private function food(string $name, int $price): FoodItem
    {
        return FoodItem::query()->create([
            'name' => $name,
            'price' => $price,
            'active' => true,
        ]);
    }

    private function successfulCreate(): array
    {
        return [
            'return_code' => 1,
            'return_message' => 'Success',
            'sub_return_code' => 1,
            'sub_return_message' => 'Success',
            'zp_trans_token' => 'token',
            'order_url' => 'https://zalopay.example.test/pay',
            'order_token' => 'order-token',
            'qr_code' => 'qr-data',
        ];
    }
}
