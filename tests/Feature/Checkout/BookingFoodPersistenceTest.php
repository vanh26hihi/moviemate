<?php

namespace Tests\Feature\Checkout;

use App\Models\FoodItem;
use App\Models\Order;
use App\Services\BookingFoodService;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFoodPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_selection_does_not_create_an_order(): void
    {
        $service = app(BookingFoodService::class);

        $order = $service->persist($service->calculate([]), [
            'customer_name' => 'Optional food customer',
        ]);

        $this->assertNull($order);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_selected_food_creates_order_and_snapshot_items_when_persist_is_called(): void
    {
        $food = FoodItem::query()->create([
            'name' => 'Combo MovieMate',
            'price' => 75_000,
            'active' => true,
        ]);
        $service = app(BookingFoodService::class);
        $breakdown = $service->calculate([
            ['food_id' => $food->id, 'quantity' => 2, 'price' => 1],
        ]);

        $order = $service->persist($breakdown, [
            'customer_name' => 'Checkout customer',
            'customer_email' => 'customer@example.test',
            'pickup_cinema_id' => 999999,
        ]);

        $this->assertNotNull($order);
        $this->assertSame(150_000, $order->subtotal);
        $this->assertSame('150000.00', $order->total_amount);
        $this->assertSame('pending', $order->status);
        $this->assertSame(app(CinemaContext::class)->id(), $order->pickup_cinema_id);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'food_item_id' => $food->id,
            'snapshot_name' => 'Combo MovieMate',
            'unit_price' => 75_000,
            'quantity' => 2,
            'line_total' => 150_000,
        ]);
    }

    public function test_existing_standalone_food_checkout_flow_still_works(): void
    {
        $food = FoodItem::query()->create([
            'name' => 'Standalone popcorn',
            'price' => 40_000,
            'active' => true,
        ]);

        $this->withSession(['food_cart' => [$food->id => 2]])
            ->post(route('foods.store'), [
                'customer_name' => 'Standalone customer',
                'customer_email' => 'standalone@example.test',
            ])
            ->assertRedirect();

        $order = Order::query()->sole();
        $this->assertNull($order->booking_id);
        $this->assertSame('80000.00', $order->total_amount);
        $this->assertSame(0, $order->subtotal);
        $this->assertSame(app(CinemaContext::class)->id(), $order->pickup_cinema_id);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'food_item_id' => $food->id,
            'quantity' => 2,
            'price' => 40_000,
            'total' => 80_000,
        ]);
    }
}
