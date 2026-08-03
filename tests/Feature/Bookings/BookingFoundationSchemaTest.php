<?php

namespace Tests\Feature\Bookings;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class BookingFoundationSchemaTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_fresh_bookings_schema_supports_guests_and_hashed_access_tokens(): void
    {
        $columns = collect(Schema::getColumns('bookings'))->keyBy('name');

        $this->assertTrue($columns['user_id']['nullable']);
        $this->assertTrue($columns->has('guest_access_token_hash'));
        $this->assertTrue($columns->has('checkout_idempotency_key_hash'));
        $this->assertTrue($this->hasIndex('bookings_guest_access_token_hash_unique', true));
    }

    public function test_checkout_idempotency_hash_has_a_unique_constraint(): void
    {
        $scenario = $this->bookingScenario(false);
        $hash = str_repeat('a', 64);
        $this->bookingForScenario($scenario, ['checkout_idempotency_key_hash' => $hash]);

        $this->expectException(QueryException::class);
        $this->bookingForScenario($scenario, ['checkout_idempotency_key_hash' => $hash]);
    }

    public function test_expiration_lookup_has_the_required_composite_index(): void
    {
        $index = collect(Schema::getIndexes('bookings'))
            ->firstWhere('name', 'bookings_expiration_lookup_index');

        $this->assertNotNull($index);
        $this->assertSame(['booking_status', 'expires_at'], $index['columns']);
    }

    public function test_migration_down_restores_original_nullable_user_relationship_with_guest_rows(): void
    {
        $scenario = $this->bookingScenario(false);
        $this->bookingForScenario($scenario);
        $migration = require database_path('migrations/2026_08_04_100000_harden_booking_foundations.php');

        $migration->down();

        $columns = collect(Schema::getColumns('bookings'))->keyBy('name');
        $this->assertTrue($columns['user_id']['nullable']);
        $this->assertFalse($columns->has('guest_access_token_hash'));
        $this->assertDatabaseHas('bookings', ['user_id' => null]);
    }

    public function test_migration_can_safely_round_trip_without_guest_rows(): void
    {
        $migration = require database_path('migrations/2026_08_04_100000_harden_booking_foundations.php');

        $migration->down();
        $columns = collect(Schema::getColumns('bookings'))->keyBy('name');
        $this->assertTrue($columns['user_id']['nullable']);
        $this->assertFalse($columns->has('guest_access_token_hash'));

        $migration->up();
        $columns = collect(Schema::getColumns('bookings'))->keyBy('name');
        $this->assertTrue($columns['user_id']['nullable']);
        $this->assertTrue($columns->has('guest_access_token_hash'));
    }

    private function hasIndex(string $name, bool $unique): bool
    {
        return collect(Schema::getIndexes('bookings'))->contains(
            fn (array $index) => $index['name'] === $name && $index['unique'] === $unique
        );
    }
}
