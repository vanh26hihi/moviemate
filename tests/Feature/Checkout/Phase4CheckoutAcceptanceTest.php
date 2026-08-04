<?php

namespace Tests\Feature\Checkout;

use App\Exceptions\BookingCheckoutConflictException;
use App\Models\Booking;
use App\Models\FoodItem;
use App\Models\Payment;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Payments\PaymentTestCase;

class Phase4CheckoutAcceptanceTest extends PaymentTestCase
{
    public function test_guest_can_complete_seat_only_checkout_without_an_empty_food_order(): void
    {
        $scenario = $this->bookingScenario(false);
        Http::fake(['*' => Http::response($this->successfulCreate(), 200)]);

        $this->startSeatOnlyDraft($scenario, 'guest-seat-only@example.test');

        $this->post(route('user.bookings.confirm'))
            ->assertRedirect('https://zalopay.example.test/pay');

        $booking = Booking::query()->with(['bookingSeats', 'payments'])->sole();
        $this->assertNull($booking->user_id);
        $this->assertNotNull($booking->guest_access_token_hash);
        $this->assertSame(50_000, $booking->seat_subtotal);
        $this->assertSame(0, $booking->food_subtotal);
        $this->assertSame(50_000, (int) $booking->total_amount);
        $this->assertCount(1, $booking->bookingSeats);
        $this->assertCount(1, $booking->payments);
        $this->assertDatabaseCount('orders', 0);
        $this->get(route('user.bookings.pending', $booking))->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['amount'] === 50_000);
    }

    public function test_logged_in_user_can_complete_seat_only_checkout_as_the_booking_owner(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        Http::fake(['*' => Http::response($this->successfulCreate(), 200)]);

        $this->actingAs($user);
        $this->startSeatOnlyDraft($scenario, $user->email);

        $this->post(route('user.bookings.confirm'))
            ->assertRedirect('https://zalopay.example.test/pay');

        $booking = Booking::query()->with(['bookingSeats', 'payments'])->sole();
        $this->assertSame($user->id, $booking->user_id);
        $this->assertSame(mb_strtolower($user->email), $booking->customer_email);
        $this->assertNull($booking->guest_access_token_hash);
        $this->assertNull($booking->guest_access_expires_at);
        $this->assertSame('pending_payment', $booking->booking_status);
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertSame(Payment::STATUS_PENDING, $booking->payments->sole()->status);
        $this->assertCount(1, $booking->bookingSeats);
        $this->assertDatabaseCount('orders', 0);
        $this->get(route('user.bookings.pending', $booking))->assertOk();
    }

    public function test_food_that_becomes_inactive_after_review_is_rejected_before_any_aggregate_is_created(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = $this->food('Review inventory combo', 30_000);
        Http::fake();

        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();
        $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'inventory@example.test',
            'food_items' => [['food_id' => $food->id, 'quantity' => 1]],
        ])->assertRedirect(route('user.bookings.review'));
        $this->get(route('user.bookings.review'))->assertOk();

        $food->update(['active' => false]);

        $this->from(route('user.bookings.review'))
            ->post(route('user.bookings.confirm'))
            ->assertRedirect(route('user.bookings.review'))
            ->assertSessionHasErrors('food_items');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_seats', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_food_deleted_after_review_is_rejected_before_any_aggregate_or_network_call(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = $this->food('Deleted inventory combo', 30_000);
        Http::fake();

        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();
        $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'deleted@example.test',
            'food_items' => [['food_id' => $food->id, 'quantity' => 1]],
        ])->assertRedirect(route('user.bookings.review'));
        $this->get(route('user.bookings.review'))->assertOk();

        $food->delete();

        $this->from(route('user.bookings.review'))
            ->post(route('user.bookings.confirm'))
            ->assertRedirect(route('user.bookings.review'))
            ->assertSessionHasErrors('food_items');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_seats', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_tampered_food_quantity_in_the_session_is_validation_not_a_server_error(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = $this->food('Quantity combo', 30_000);
        Http::fake();

        $this->startFoodDraft($scenario, $food, 'quantity@example.test');
        $draft = session('booking_checkout_draft');
        $draft['food_items'][0]['quantity'] = 999;

        $this->withSession(['booking_checkout_draft' => $draft])
            ->from(route('user.bookings.review'))
            ->post(route('user.bookings.confirm'))
            ->assertRedirect(route('user.bookings.review'))
            ->assertSessionHasErrors('food_items');

        $this->assertNoCheckoutAggregateOrNetworkCall();
    }

    public function test_fractional_database_food_price_is_validation_not_a_server_error(): void
    {
        $scenario = $this->bookingScenario(false);
        $food = $this->food('Fractional combo', 30_000);
        Http::fake();

        $this->startFoodDraft($scenario, $food, 'fractional@example.test');
        DB::table('food_items')->where('id', $food->id)->update(['price' => '30000.50']);

        $this->from(route('user.bookings.review'))
            ->post(route('user.bookings.confirm'))
            ->assertRedirect(route('user.bookings.review'))
            ->assertSessionHasErrors('food_items');

        $this->assertNoCheckoutAggregateOrNetworkCall();
    }

    public function test_seat_locked_by_another_checkout_after_review_is_rejected_before_payment(): void
    {
        $scenario = $this->bookingScenario(false);
        Http::fake();

        $this->startSeatOnlyDraft($scenario, 'raced@example.test');
        $this->get(route('user.bookings.review'))->assertOk();

        $competing = $this->reserve(
            $scenario,
            [$scenario['seats'][0]->id],
            null,
            app(BookingTokenService::class)->issueCheckoutToken(),
        );

        $this->from(route('user.bookings.review'))
            ->post(route('user.bookings.confirm'))
            ->assertRedirect(route('user.bookings.review'))
            ->assertSessionHasErrors('seat_ids');

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', ['id' => $competing->booking->id]);
        $this->assertDatabaseCount('booking_seats', 1);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_same_checkout_token_and_payload_conflict_when_the_actor_changes(): void
    {
        $scenario = $this->bookingScenario(false);
        $user = $this->userWithRole('user');
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $service = app(BookingCheckoutService::class);

        $guest = $service->createPendingBooking(
            $scenario['showtime']->id,
            [$scenario['seats'][0]->id],
            null,
            'same@example.test',
            $token,
        );

        try {
            $service->createPendingBooking(
                $scenario['showtime']->id,
                [$scenario['seats'][0]->id],
                $user->id,
                'same@example.test',
                $token,
            );
            $this->fail('Changing the actor must conflict with the original checkout fingerprint.');
        } catch (BookingCheckoutConflictException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', [
            'id' => $guest->booking->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseCount('booking_seats', 1);
    }

    public function test_checkout_page_rate_limits_a_guest_by_network_identity(): void
    {
        $scenario = $this->bookingScenario(false);
        $url = route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.41']);

        for ($request = 1; $request <= 30; $request++) {
            $this->get($url)->assertOk();
        }

        $this->get($url)->assertTooManyRequests();
    }

    public function test_checkout_page_rate_limits_a_user_even_when_the_ip_changes(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $url = route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]);
        $this->actingAs($user);

        for ($request = 1; $request <= 30; $request++) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.$request"])
                ->get($url)
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->get($url)
            ->assertTooManyRequests();
    }

    private function startSeatOnlyDraft(array $scenario, string $email): void
    {
        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();

        $this->post(route('user.bookings.food.store'), [
            'customer_email' => $email,
            'skip_food' => true,
        ])->assertRedirect(route('user.bookings.review'));
    }

    private function startFoodDraft(array $scenario, FoodItem $food, string $email): void
    {
        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();

        $this->post(route('user.bookings.food.store'), [
            'customer_email' => $email,
            'food_items' => [['food_id' => $food->id, 'quantity' => 1]],
        ])->assertRedirect(route('user.bookings.review'));
        $this->get(route('user.bookings.review'))->assertOk();
    }

    private function assertNoCheckoutAggregateOrNetworkCall(): void
    {
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_seats', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
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
            'zp_trans_token' => 'acceptance-token',
            'order_url' => 'https://zalopay.example.test/pay',
            'order_token' => 'acceptance-order-token',
            'qr_code' => 'acceptance-qr',
        ];
    }
}
