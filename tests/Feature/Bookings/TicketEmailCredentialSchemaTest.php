<?php

namespace Tests\Feature\Bookings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class TicketEmailCredentialSchemaTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_forward_migration_adds_nullable_ticket_email_credential_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('bookings', [
            'ticket_email_token_nonce',
            'ticket_email_token_hash',
            'ticket_email_token_expires_at',
        ]));

        $booking = $this->bookingForScenario($this->bookingScenario(false));
        $this->assertNull($booking->getRawOriginal('ticket_email_token_nonce'));
        $this->assertNull($booking->getRawOriginal('ticket_email_token_hash'));
        $this->assertNull($booking->getRawOriginal('ticket_email_token_expires_at'));

        $index = collect(Schema::getIndexes('bookings'))->first(
            fn (array $index): bool => ($index['name'] ?? null)
                === 'bookings_ticket_email_token_hash_unique',
        );
        $this->assertNotNull($index);
        $this->assertTrue($index['unique']);
    }

    public function test_down_refuses_to_destroy_ticket_email_credential_data(): void
    {
        $booking = $this->bookingForScenario($this->bookingScenario(false));
        $booking->forceFill([
            'ticket_email_token_nonce' => str_repeat('n', 43),
            'ticket_email_token_hash' => hash('sha256', 'ticket-email-token'),
            'ticket_email_token_expires_at' => now()->addHour(),
        ])->save();

        $migration = $this->migration();

        try {
            $migration->down();
            $this->fail('Expected rollback with credential data to be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('credential data exists', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('bookings', 'ticket_email_token_hash'));
        $this->assertSame(
            hash('sha256', 'ticket-email-token'),
            $booking->fresh()->getRawOriginal('ticket_email_token_hash'),
        );
    }

    public function test_down_removes_only_empty_columns_and_up_can_restore_them(): void
    {
        $migration = $this->migration();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('bookings', 'ticket_email_token_nonce'));
        $this->assertFalse(Schema::hasColumn('bookings', 'ticket_email_token_hash'));
        $this->assertFalse(Schema::hasColumn('bookings', 'ticket_email_token_expires_at'));

        $migration->up();
        $this->assertTrue(Schema::hasColumns('bookings', [
            'ticket_email_token_nonce',
            'ticket_email_token_hash',
            'ticket_email_token_expires_at',
        ]));
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_08_04_124000_add_ticket_email_access_credentials_to_bookings.php',
        );
    }
}
