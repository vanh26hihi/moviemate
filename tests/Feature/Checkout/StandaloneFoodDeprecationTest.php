<?php

namespace Tests\Feature\Checkout;

use App\Models\FoodItem;
use App\Models\Order;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandaloneFoodDeprecationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_store_is_gone_and_cannot_create_paid_orders_or_items(): void
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
                'pickup_cinema_id' => 999999,
            ])
            ->assertGone()
            ->assertSee('MovieMate đã ngừng luồng đặt đồ ăn riêng');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(0, Order::query()->where('status', 'paid')->count());
    }

    public function test_all_legacy_standalone_mutation_steps_are_gone(): void
    {
        $this->post(route('foods.add'), ['food_id' => 1, 'quantity' => 1])->assertGone();
        $this->get(route('foods.cart'))->assertGone();
        $this->get(route('foods.checkout'))->assertGone();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_numeric_success_route_never_exposes_order_data(): void
    {
        $order = Order::query()->create([
            'customer_name' => 'Private customer',
            'customer_email' => 'private@example.test',
            'pickup_cinema_id' => app(CinemaContext::class)->id(),
            'total_amount' => 123_000,
            'status' => 'paid',
        ]);

        $existing = $this->get(route('foods.success', $order->id));
        $missing = $this->get(route('foods.success', 999999));

        $existing->assertGone()->assertDontSee('Private customer')->assertDontSee('123.000');
        $missing->assertGone();
        $this->assertSame($existing->getContent(), $missing->getContent());
    }

    public function test_food_menu_still_lists_only_active_items_and_links_to_showtimes(): void
    {
        FoodItem::query()->create(['name' => 'Active combo', 'price' => 50_000, 'active' => true]);
        FoodItem::query()->create(['name' => 'Inactive combo', 'price' => 50_000, 'active' => false]);

        $this->get(route('foods.index'))
            ->assertOk()
            ->assertSee('Active combo')
            ->assertDontSee('Inactive combo')
            ->assertSee('Chọn phim và suất chiếu')
            ->assertDontSee('foods/add', false);
    }
}
