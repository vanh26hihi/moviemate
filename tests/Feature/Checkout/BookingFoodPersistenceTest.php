<?php

namespace Tests\Feature\Checkout;

use App\Models\FoodItem;
use App\Services\BookingFoodService;
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

    public function test_nonempty_food_cannot_be_persisted_without_a_unified_booking(): void
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unified booking checkout');

        $service->persist($breakdown);
    }
}
