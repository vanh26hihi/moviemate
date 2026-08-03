<?php

namespace Tests\Feature\Bookings;

use App\Services\BookingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class GuestBookingAccessTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_guest_cannot_view_a_guest_booking_by_sequential_id_alone(): void
    {
        [$booking] = $this->guestBooking();

        $this->get(route('user.bookings.ticket', $booking))->assertNotFound();
    }

    public function test_missing_guest_token_is_rejected_on_success_page(): void
    {
        [$booking] = $this->guestBooking();

        $this->get(route('user.bookings.success', $booking))->assertNotFound();
    }

    public function test_wrong_guest_token_is_rejected(): void
    {
        [$booking] = $this->guestBooking();

        $this->get(route('user.bookings.ticket', [
            'booking' => $booking,
            'guest_token' => 'wrong-token',
        ]))->assertNotFound();
    }

    public function test_correct_guest_token_can_view_success_and_ticket_pages(): void
    {
        [$booking, $rawToken] = $this->guestBooking();
        $parameters = ['booking' => $booking, 'guest_token' => $rawToken];

        $this->get(route('user.bookings.success', $parameters))->assertOk();
        $this->get(route('user.bookings.ticket', $parameters))->assertOk();
    }

    public function test_token_for_one_guest_booking_cannot_open_another_booking(): void
    {
        [, $firstToken] = $this->guestBooking();
        [$secondBooking] = $this->guestBooking();

        $this->get(route('user.bookings.ticket', [
            'booking' => $secondBooking,
            'guest_token' => $firstToken,
        ]))->assertNotFound();
    }

    public function test_raw_guest_token_is_never_stored_in_the_database(): void
    {
        [$booking, $rawToken] = $this->guestBooking();
        $booking->refresh();

        $this->assertNotSame($rawToken, $booking->getRawOriginal('guest_access_token_hash'));
        $this->assertSame(hash('sha256', $rawToken), $booking->getRawOriginal('guest_access_token_hash'));
        $this->assertStringNotContainsString($rawToken, json_encode($booking->getAttributes()));
    }

    public function test_token_hash_verification_accepts_only_the_matching_value(): void
    {
        $tokens = app(BookingTokenService::class);
        $raw = str()->random(64);
        $hash = $tokens->hash($raw);

        $this->assertTrue($tokens->verifyHash($hash, $raw));
        $this->assertFalse($tokens->verifyHash($hash, $raw.'x'));
        $this->assertFalse($tokens->verifyHash(null, $raw));
    }

    public function test_logged_in_owner_can_still_view_their_booking_without_guest_token(): void
    {
        $scenario = $this->bookingScenario(false);
        $owner = $this->userWithRole('user');
        $booking = $this->bookingForScenario($scenario, ['user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))->assertOk();
    }

    public function test_logged_in_user_cannot_view_another_users_booking(): void
    {
        $scenario = $this->bookingScenario(false);
        $owner = $this->userWithRole('user');
        $other = $this->userWithRole('user');
        $booking = $this->bookingForScenario($scenario, ['user_id' => $owner->id]);

        $this->actingAs($other)->get(route('user.bookings.ticket', $booking))->assertForbidden();
    }

    public function test_user_with_bookings_view_permission_keeps_existing_access(): void
    {
        $scenario = $this->bookingScenario(false);
        $owner = $this->userWithRole('user');
        $manager = $this->userWithRole('manager');
        $booking = $this->bookingForScenario($scenario, ['user_id' => $owner->id]);

        $this->actingAs($manager)->get(route('user.bookings.ticket', $booking))->assertOk();
    }

    private function guestBooking(): array
    {
        $scenario = $this->bookingScenario(false);
        $rawToken = app(BookingTokenService::class)->guestAccessTokenForCheckout(
            app(BookingTokenService::class)->issueCheckoutToken()
        );
        $booking = $this->bookingForScenario($scenario, [
            'guest_access_token_hash' => hash('sha256', $rawToken),
        ]);

        return [$booking, $rawToken];
    }
}
