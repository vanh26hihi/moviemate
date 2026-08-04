<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $booking = $this->bookingForScenario($this->bookingScenario(false));
        $order = Order::query()->create([
            'booking_id' => $booking->id,
            'customer_name' => 'Rollback history',
            'pickup_cinema_id' => $booking->showtime->cinema_id,
            'total_amount' => 0,
            'subtotal' => 0,
            'status' => 'expired',
        ]);

        $migration->down();
        $this->assertPhaseFourColumnsAreMissing();
        $this->assertSame(1, DB::table('bookings')->where('id', $booking->id)->count());
        $this->assertSame(1, DB::table('orders')->where('id', $order->id)->count());

        $migration->up();
        $this->assertPhaseFourColumnsExist();
        $this->assertSame(1, DB::table('bookings')->where('id', $booking->id)->count());
        $this->assertSame(1, DB::table('orders')->where('id', $order->id)->count());

        $foreignKeys = collect(Schema::getForeignKeys('orders'));
        $bookingForeignKey = $foreignKeys->first(fn (array $key) => $key['columns'] === ['booking_id']);
        $this->assertNotNull($bookingForeignKey);
        $this->assertSame('set null', strtolower((string) $bookingForeignKey['on_delete']));
        $this->assertTrue(collect(Schema::getIndexes('orders'))->contains(
            fn (array $index) => $index['name'] === 'orders_booking_id_unique' && $index['unique'],
        ));
    }

    public function test_down_handles_a_missing_foreign_key_while_the_unique_index_remains(): void
    {
        $migration = require database_path('migrations/2026_08_04_120000_add_checkout_pricing_and_food_snapshots.php');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
        });

        $this->assertTrue(collect(Schema::getIndexes('orders'))->contains(
            fn (array $index): bool => $index['name'] === 'orders_booking_id_unique',
        ));

        $migration->down();
        $this->assertPhaseFourColumnsAreMissing();

        $migration->up();
        $this->assertPhaseFourColumnsExist();
    }

    public function test_down_is_idempotent_for_missing_indexes_and_columns_after_partial_ddl(): void
    {
        $migration = require database_path('migrations/2026_08_04_120000_add_checkout_pricing_and_food_snapshots.php');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_booking_id_unique');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('subtotal');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('snapshot_name');
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });

        $migration->down();
        $migration->down();
        $this->assertPhaseFourColumnsAreMissing();

        $migration->up();
        $this->assertPhaseFourColumnsExist();
    }

    public function test_down_declares_foreign_key_drop_before_unique_index_drop(): void
    {
        $source = file_get_contents(
            database_path('migrations/2026_08_04_120000_add_checkout_pricing_and_food_snapshots.php'),
        );

        $foreignDrop = strpos($source, '$table->dropForeign(self::ORDERS_BOOKING_FOREIGN)');
        $uniqueDrop = strpos($source, '$table->dropUnique(self::ORDERS_BOOKING_UNIQUE)');

        $this->assertNotFalse($foreignDrop);
        $this->assertNotFalse($uniqueDrop);
        $this->assertLessThan($uniqueDrop, $foreignDrop);
    }

    private function assertPhaseFourColumnsAreMissing(): void
    {
        foreach (['seat_subtotal', 'food_subtotal', 'currency'] as $column) {
            $this->assertFalse(Schema::hasColumn('bookings', $column));
        }
        foreach (['booking_id', 'subtotal'] as $column) {
            $this->assertFalse(Schema::hasColumn('orders', $column));
        }
        foreach (['snapshot_name', 'unit_price', 'line_total'] as $column) {
            $this->assertFalse(Schema::hasColumn('order_items', $column));
        }
    }

    private function assertPhaseFourColumnsExist(): void
    {
        $this->assertTrue(Schema::hasColumns('bookings', [
            'seat_subtotal', 'food_subtotal', 'currency',
        ]));
        $this->assertTrue(Schema::hasColumns('orders', ['booking_id', 'subtotal']));
        $this->assertTrue(Schema::hasColumns('order_items', [
            'snapshot_name', 'unit_price', 'line_total',
        ]));
    }
}
