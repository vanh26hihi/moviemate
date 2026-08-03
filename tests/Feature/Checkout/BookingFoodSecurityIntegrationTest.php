<?php

namespace Tests\Feature\Checkout;

use App\Exceptions\BookingCheckoutConflictException;
use App\Models\FoodItem;
use App\Models\Order;
use App\Services\BookingCheckoutService;
use App\Services\BookingExpirationService;
use App\Services\BookingFoodService;
use App\Services\BookingTokenService;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class BookingFoodSecurityIntegrationTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_reordered_food_replays_but_changed_quantity_conflicts(): void
    {
        $scenario = $this->bookingScenario();
        $firstFood = $this->food('Popcorn', 40_000);
        $secondFood = $this->food('Drink', 20_000);
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $seatIds = [$scenario['seats'][0]->id];

        $first = $this->checkout($scenario, $seatIds, $token, [
            ['food_id' => $firstFood->id, 'quantity' => 2, 'price' => 1],
            ['food_id' => $secondFood->id, 'quantity' => 1],
        ]);
        $replay = $this->checkout($scenario, $seatIds, $token, [
            ['food_id' => $secondFood->id, 'quantity' => '1', 'price' => 999999],
            ['food_id' => $firstFood->id, 'quantity' => '2', 'line_total' => 1],
        ]);

        $this->assertSame($first->booking->id, $replay->booking->id);
        $this->assertTrue($replay->replayed);
        $this->assertDatabaseCount('orders', 1);

        $this->expectException(BookingCheckoutConflictException::class);
        $this->checkout($scenario, $seatIds, $token, [
            ['food_id' => $firstFood->id, 'quantity' => 3],
            ['food_id' => $secondFood->id, 'quantity' => 1],
        ]);
    }

    public function test_changed_food_conflicts_with_the_same_checkout_token(): void
    {
        $scenario = $this->bookingScenario();
        $firstFood = $this->food('Popcorn', 40_000);
        $secondFood = $this->food('Drink', 20_000);
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $seatIds = [$scenario['seats'][0]->id];

        $this->checkout($scenario, $seatIds, $token, [
            ['food_id' => $firstFood->id, 'quantity' => 1],
        ]);

        $this->expectException(BookingCheckoutConflictException::class);
        $this->checkout($scenario, $seatIds, $token, [
            ['food_id' => $secondFood->id, 'quantity' => 1],
        ]);
    }

    public function test_unified_checkout_uses_integer_server_prices_and_creates_a_pending_canonical_order(): void
    {
        $scenario = $this->bookingScenario();
        $food = $this->food('Combo', 75_000);
        $token = app(BookingTokenService::class)->issueCheckoutToken();

        $result = $this->checkout($scenario, [$scenario['seats'][0]->id], $token, [
            [
                'food_id' => $food->id,
                'quantity' => 2,
                'price' => 1,
                'unit_price' => 1,
                'line_total' => 2,
                'guest_access_token' => 'must-not-persist',
            ],
        ]);

        $booking = $result->booking->fresh();
        $order = Order::query()->with('items')->sole();
        $this->assertSame(50_000, $booking->seat_subtotal);
        $this->assertSame(150_000, $booking->food_subtotal);
        $this->assertSame('200000.00', $booking->total_amount);
        $this->assertSame($booking->seat_subtotal + $booking->food_subtotal, (int) $booking->total_amount);
        $this->assertSame('VND', $booking->currency);
        $this->assertSame('pending', $order->status);
        $this->assertSame($booking->id, $order->booking_id);
        $this->assertSame(app(CinemaContext::class)->id(), $order->pickup_cinema_id);
        $this->assertSame(150_000, $order->subtotal);
        $this->assertSame(75_000, $order->items->sole()->unit_price);
        $this->assertSame(150_000, $order->items->sole()->line_total);

        $serializedOrder = json_encode([$order->getAttributes(), $order->items->first()->getAttributes()]);
        $this->assertStringNotContainsString('must-not-persist', $serializedOrder);
        $this->assertStringNotContainsString((string) $result->guestAccessToken, route('foods.success', $order));
    }

    public function test_http_checkout_ignores_frontend_totals_prices_tokens_and_pickup_cinema(): void
    {
        $scenario = $this->bookingScenario();
        $food = $this->food('HTTP combo', 30_000);

        $this->post(route('user.bookings.store'), [
            'showtime_id' => $scenario['showtime']->id,
            'seat_ids' => [$scenario['seats'][0]->id],
            'customer_email' => 'guest@example.test',
            'checkout_token' => app(BookingTokenService::class)->issueCheckoutToken(),
            'total_amount' => 1,
            'pickup_cinema_id' => 999999,
            'guest_access_token' => 'frontend-secret',
            'food_items' => [[
                'food_id' => $food->id,
                'quantity' => 2,
                'price' => 1,
                'unit_price' => 1,
                'line_total' => 1,
            ]],
        ])->assertOk()->assertViewIs('user.bookings.guest-handoff');

        $booking = $scenario['showtime']->bookings()->sole();
        $order = Order::query()->with('items')->sole();
        $this->assertSame('110000.00', $booking->total_amount);
        $this->assertSame(60_000, $booking->food_subtotal);
        $this->assertSame(60_000, $order->subtotal);
        $this->assertSame(30_000, $order->items->sole()->unit_price);
        $this->assertSame(app(CinemaContext::class)->id(), $order->pickup_cinema_id);
        $this->assertStringNotContainsString(
            'frontend-secret',
            json_encode([$order->getAttributes(), $order->items->first()->getAttributes()]),
        );
    }

    public function test_empty_and_zero_food_are_equivalent_and_do_not_create_an_order(): void
    {
        $scenario = $this->bookingScenario();
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $seatIds = [$scenario['seats'][0]->id];

        $first = $this->checkout($scenario, $seatIds, $token, []);
        $replay = $this->checkout($scenario, $seatIds, $token, [
            ['food_id' => 999999, 'quantity' => 0, 'price' => 999999],
        ]);

        $this->assertSame($first->booking->id, $replay->booking->id);
        $this->assertTrue($replay->replayed);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_couple_pair_is_priced_once_and_both_snapshots_sum_to_pair_total(): void
    {
        $scenario = $this->bookingScenario();
        $pairIds = $scenario['seats']->where('type', 'couple')->pluck('id')->all();

        $booking = $this->checkout(
            $scenario,
            $pairIds,
            app(BookingTokenService::class)->issueCheckoutToken(),
            [],
        )->booking->fresh();

        $this->assertSame(100_000, $booking->seat_subtotal);
        $this->assertSame('100000.00', $booking->total_amount);
        $this->assertSame(100_000, (int) $booking->bookingSeats()->sum('price'));
    }

    public function test_expiration_preserves_food_order_and_item_history(): void
    {
        $scenario = $this->bookingScenario();
        $food = $this->food('History combo', 60_000);
        $booking = $this->checkout(
            $scenario,
            [$scenario['seats'][0]->id],
            app(BookingTokenService::class)->issueCheckoutToken(),
            [['food_id' => $food->id, 'quantity' => 1]],
        )->booking;
        $order = Order::query()->sole();
        $itemId = $order->items()->sole()->id;
        $booking->update(['expires_at' => now()->subMinute()]);

        $this->assertTrue(app(BookingExpirationService::class)->expire($booking->id));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'booking_id' => $booking->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('order_items', ['id' => $itemId, 'order_id' => $order->id]);
    }

    public function test_cancelled_booking_contract_transitions_order_without_deleting_history(): void
    {
        $scenario = $this->bookingScenario();
        $food = $this->food('Cancelled combo', 60_000);
        $booking = $this->checkout(
            $scenario,
            [$scenario['seats'][0]->id],
            app(BookingTokenService::class)->issueCheckoutToken(),
            [['food_id' => $food->id, 'quantity' => 1]],
        )->booking;
        $order = Order::query()->sole();
        $booking->update(['booking_status' => 'cancelled']);

        $this->assertSame(1, app(BookingFoodService::class)->transitionForBooking($booking, 'cancelled'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertDatabaseCount('order_items', 1);
    }

    private function checkout(array $scenario, array $seatIds, string $token, array $foodSelection)
    {
        return app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            $seatIds,
            null,
            'guest@example.test',
            $token,
            $foodSelection,
        );
    }

    private function food(string $name, int $price): FoodItem
    {
        return FoodItem::query()->create([
            'name' => $name,
            'price' => $price,
            'active' => true,
        ]);
    }
}
