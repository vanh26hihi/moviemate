<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_database_contains_the_restored_application_schema(): void
    {
        foreach ([
            'roles',
            'users',
            'movies',
            'genres',
            'movie_genre',
            'cinemas',
            'room_types',
            'rooms',
            'seat_types',
            'seats',
            'showtimes',
            'bookings',
            'booking_seats',
            'payments',
            'reviews',
            'ai_chats',
            'ai_recommendations',
            'food_items',
            'orders',
            'order_items',
            'cache',
            'jobs',
            'sessions',
            'cinema_consolidation_mappings',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}].");
        }

        $this->assertTrue(Schema::hasColumns('users', [
            'phone', 'role_id', 'avatar', 'status', 'email_verified_at',
        ]));
        $this->assertTrue(Schema::hasColumns('cinemas', [
            'canonical_key', 'school_name', 'country', 'district', 'latitude', 'longitude',
            'is_primary', 'archived_at',
        ]));
        $this->assertTrue(Schema::hasColumns('rooms', [
            'cinema_id', 'code', 'room_type_id', 'total_seats', 'status',
        ]));
        $this->assertTrue(Schema::hasColumns('seats', [
            'room_id',
            'seat_type_id',
            'pair_code',
            'pair_position',
            'row_label',
            'seat_number',
            'x_position',
            'y_position',
            'is_center',
        ]));
        $this->assertTrue(Schema::hasColumns('showtimes', [
            'movie_id', 'cinema_id', 'room_id', 'show_date', 'show_time', 'status',
        ]));
        $this->assertTrue(Schema::hasColumns('bookings', [
            'user_id',
            'showtime_id',
            'payment_method',
            'payment_status',
            'booking_status',
            'expires_at',
            'paid_at',
            'customer_email',
        ]));
        $this->assertTrue(Schema::hasColumns('booking_seats', [
            'booking_id', 'showtime_id', 'seat_id', 'active_lock_key', 'price',
        ]));
        $this->assertTrue(Schema::hasColumns('payments', [
            'booking_id',
            'provider',
            'payment_method',
            'order_code',
            'amount',
            'status',
            'transaction_code',
            'transaction_status',
            'payment_url',
            'response_code',
            'card_type',
            'bank_code',
            'transaction_id',
            'paid_at',
            'raw_request',
            'raw_response',
        ]));
        $this->assertTrue(Schema::hasColumns('orders', [
            'user_id', 'pickup_cinema_id', 'total_amount', 'status',
        ]));
        $this->assertTrue(Schema::hasColumns('order_items', [
            'order_id', 'food_item_id', 'quantity', 'price', 'total',
        ]));
    }

    public function test_fresh_database_contains_important_foreign_keys_and_unique_indexes(): void
    {
        $this->assertForeignKey('users', 'role_id', 'roles');
        $this->assertForeignKey('rooms', 'cinema_id', 'cinemas');
        $this->assertForeignKey('seats', 'room_id', 'rooms');
        $this->assertForeignKey('showtimes', 'room_id', 'rooms');
        $this->assertForeignKey('bookings', 'showtime_id', 'showtimes');
        $this->assertForeignKey('booking_seats', 'booking_id', 'bookings');
        $this->assertForeignKey('booking_seats', 'showtime_id', 'showtimes');
        $this->assertForeignKey('booking_seats', 'seat_id', 'seats');
        $this->assertForeignKey('payments', 'booking_id', 'bookings');
        $this->assertForeignKey('order_items', 'order_id', 'orders');
        $this->assertForeignKey('order_items', 'food_item_id', 'food_items');

        $this->assertUniqueIndex('rooms', ['cinema_id', 'code']);
        $this->assertUniqueIndex('cinemas', ['canonical_key']);
        $this->assertUniqueIndex('cinema_consolidation_mappings', ['entity_type', 'entity_id']);
        $this->assertUniqueIndex('seats', ['room_id', 'seat_code']);
        $this->assertUniqueIndex('bookings', ['booking_code']);
        $this->assertUniqueIndex('booking_seats', [
            'showtime_id', 'seat_id', 'active_lock_key',
        ]);
        $this->assertUniqueIndex('payments', ['order_code']);
    }

    private function assertForeignKey(string $table, string $column, string $foreignTable): void
    {
        $foreignKeys = collect(Schema::getForeignKeys($table));

        $this->assertTrue(
            $foreignKeys->contains(fn (array $foreignKey) => in_array($column, $foreignKey['columns'], true)
                && $foreignKey['foreign_table'] === $foreignTable
            ),
            "Missing foreign key [{$table}.{$column} -> {$foreignTable}]."
        );
    }

    private function assertUniqueIndex(string $table, array $columns): void
    {
        $indexes = collect(Schema::getIndexes($table));

        $this->assertTrue(
            $indexes->contains(fn (array $index) => $index['unique'] && $index['columns'] === $columns
            ),
            'Missing unique index ['.$table.'.'.implode(',', $columns).'].'
        );
    }
}
