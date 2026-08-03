<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class FoodPricingSchemaTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_booking_totals_currency_and_food_snapshot_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('bookings', [
            'seat_subtotal', 'food_subtotal', 'currency',
        ]));
        $this->assertTrue(Schema::hasColumns('orders', ['booking_id', 'subtotal']));
        $this->assertTrue(Schema::hasColumns('order_items', [
            'snapshot_name', 'unit_price', 'line_total',
        ]));

        $booking = $this->bookingForScenario($this->bookingScenario(false))->refresh();
        $this->assertSame(0, (int) $booking->seat_subtotal);
        $this->assertSame(0, (int) $booking->food_subtotal);
        $this->assertSame('VND', $booking->currency);
    }

    public function test_only_one_unified_order_can_reference_a_booking(): void
    {
        $booking = $this->bookingForScenario($this->bookingScenario(false));
        $attributes = [
            'booking_id' => $booking->id,
            'customer_name' => 'Unified order',
            'pickup_cinema_id' => $booking->showtime->cinema_id,
            'total_amount' => 0,
            'subtotal' => 0,
            'status' => 'pending',
        ];
        Order::query()->create($attributes);

        $this->expectException(QueryException::class);
        Order::query()->create($attributes);
    }

    public function test_booking_delete_preserves_order_history_and_nulls_reference(): void
    {
        $booking = $this->bookingForScenario($this->bookingScenario(false));
        $order = Order::query()->create([
            'booking_id' => $booking->id,
            'customer_name' => 'Historical order',
            'pickup_cinema_id' => $booking->showtime->cinema_id,
            'total_amount' => 0,
            'subtotal' => 0,
            'status' => 'expired',
        ]);

        $booking->delete();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'booking_id' => null, 'status' => 'expired']);
    }

    public function test_migration_can_roundtrip_safely(): void
    {
        $migration = require database_path('migrations/2026_08_04_120000_add_checkout_pricing_and_food_snapshots.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('bookings', 'seat_subtotal'));
        $this->assertFalse(Schema::hasColumn('orders', 'booking_id'));
        $this->assertFalse(Schema::hasColumn('order_items', 'snapshot_name'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('bookings', 'seat_subtotal'));
        $this->assertTrue(Schema::hasColumn('orders', 'booking_id'));
        $this->assertTrue(Schema::hasColumn('order_items', 'snapshot_name'));

        $foreignKeys = collect(Schema::getForeignKeys('orders'));
        $bookingForeignKey = $foreignKeys->first(fn (array $key) => $key['columns'] === ['booking_id']);
        $this->assertNotNull($bookingForeignKey);
        $this->assertSame('set null', strtolower((string) $bookingForeignKey['on_delete']));
        $this->assertTrue(collect(Schema::getIndexes('orders'))->contains(
            fn (array $index) => $index['name'] === 'orders_booking_id_unique' && $index['unique'],
        ));
    }
}
