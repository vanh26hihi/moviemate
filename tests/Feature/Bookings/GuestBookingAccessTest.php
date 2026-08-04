<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
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

    public function test_guest_cannot_view_a_booking_by_sequential_id_or_query_token(): void
    {
        [$booking, $rawToken] = $this->guestBooking();

        $this->get(route('user.bookings.ticket', $booking))->assertNotFound();
        $this->get(route('user.bookings.ticket', [
            'booking' => $booking,
            'guest_token' => $rawToken,
        ]))->assertNotFound();
    }

    public function test_wrong_token_cannot_create_a_guest_session_capability(): void
    {
        [$booking] = $this->guestBooking();

        $this->post(route('user.bookings.access.exchange', $booking), [
            'token' => 'wrong-token',
            'destination' => 'ticket',
        ])->assertNotFound();
        $this->get(route('user.bookings.ticket', $booking))->assertNotFound();
    }

    public function test_correct_token_is_exchanged_for_a_scoped_session_capability(): void
    {
        [$booking, $rawToken] = $this->guestBooking();

        $this->post(route('user.bookings.access.exchange', $booking), [
            'token' => $rawToken,
            'destination' => 'success',
        ])->assertOk()->assertJsonPath(
            'redirect_url',
            route('user.bookings.success', $booking),
        );

        $this->get(route('user.bookings.success', $booking))->assertOk();
        $this->get(route('user.bookings.ticket', $booking))->assertOk();
    }

    public function test_pending_booking_pages_never_render_a_usable_ticket(): void
    {
        [$booking, $rawToken] = $this->guestBooking();
        $this->exchange($booking, $rawToken);

        $this->get(route('user.bookings.success', $booking))
            ->assertOk()
            ->assertDontSee('Xem vé QR của tôi');

        $this->get(route('user.bookings.ticket', $booking))
            ->assertOk()
            ->assertDontSee('data-qr-value', false)
            ->assertDontSee('data-ticket-download', false)
            ->assertSee('không có vé QR khả dụng');
    }

    public function test_used_booking_is_history_without_a_usable_qr_or_download(): void
    {
        [$booking, $rawToken] = $this->guestBooking();
        $booking->forceFill([
            'payment_status' => 'paid',
            'booking_status' => 'used',
            'paid_at' => now()->subHour(),
            'used_at' => now(),
        ])->save();
        $this->exchange($booking, $rawToken);

        $this->get(route('user.bookings.ticket', $booking))
            ->assertOk()
            ->assertDontSee('data-qr-value', false)
            ->assertDontSee('data-ticket-download', false)
            ->assertSee('Đã sử dụng');
    }

    public function test_token_for_one_guest_booking_cannot_open_another_booking(): void
    {
        [, $firstToken] = $this->guestBooking();
        [$secondBooking] = $this->guestBooking();

        $this->post(route('user.bookings.access.exchange', $secondBooking), [
            'token' => $firstToken,
            'destination' => 'ticket',
        ])->assertNotFound();
        $this->get(route('user.bookings.ticket', $secondBooking))->assertNotFound();
    }

    public function test_expired_guest_link_cannot_be_shown_or_exchanged(): void
    {
        [$booking, $rawToken] = $this->guestBooking();
        $booking->update(['guest_access_expires_at' => now()->subSecond()]);

        $this->get(route('user.bookings.access.show', $booking))->assertNotFound();
        $this->post(route('user.bookings.access.exchange', $booking), [
            'token' => $rawToken,
            'destination' => 'ticket',
        ])->assertNotFound();
    }

    public function test_expired_or_rotated_access_revokes_an_existing_session_capability(): void
    {
        [$booking, $rawToken] = $this->guestBooking();
        $this->exchange($booking, $rawToken);

        $booking->update(['guest_access_token_hash' => hash('sha256', 'rotated-token')]);
        $this->get(route('user.bookings.ticket', $booking))->assertNotFound();

        [$expiringBooking, $expiringToken] = $this->guestBooking();
        $this->exchange($expiringBooking, $expiringToken);
        $expiringBooking->update(['guest_access_expires_at' => now()->subSecond()]);
        $this->get(route('user.bookings.ticket', $expiringBooking))->assertNotFound();
    }

    public function test_guest_checkout_uses_fragment_handoff_and_protected_response_headers(): void
    {
        $scenario = $this->bookingScenario();
        $checkoutToken = app(BookingTokenService::class)->issueCheckoutToken();
        $guestToken = app(BookingTokenService::class)->guestAccessTokenForCheckout($checkoutToken);

        $response = $this->post(route('user.bookings.store'), [
            'showtime_id' => $scenario['showtime']->id,
            'seat_ids' => [$scenario['seats'][0]->id],
            'customer_email' => 'guest@example.test',
            'checkout_token' => $checkoutToken,
        ]);

        $response->assertOk()
            ->assertViewIs('user.bookings.guest-handoff')
            ->assertViewHas('guestAccessToken', $guestToken)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('new URLSearchParams({ token, destination })', false)
            ->assertDontSee('guest_token=', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $booking = Booking::query()->sole();
        $this->assertSame(
            route('user.bookings.access.show', $booking),
            $response->viewData('accessUrl'),
        );
        $this->assertTrue($booking->guest_access_expires_at->isFuture());
    }

    public function test_access_page_removes_referrer_and_cache_leakage(): void
    {
        [$booking] = $this->guestBooking();

        $response = $this->get(route('user.bookings.access.show', $booking));

        $response->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
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

    public function test_logged_in_owner_can_still_view_their_booking_without_guest_access(): void
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

    private function exchange(Booking $booking, string $rawToken): void
    {
        $this->post(route('user.bookings.access.exchange', $booking), [
            'token' => $rawToken,
            'destination' => 'ticket',
        ])->assertOk();
    }

    private function guestBooking(): array
    {
        $scenario = $this->bookingScenario(false);
        $rawToken = app(BookingTokenService::class)->guestAccessTokenForCheckout(
            app(BookingTokenService::class)->issueCheckoutToken()
        );
        $booking = $this->bookingForScenario($scenario, [
            'guest_access_token_hash' => hash('sha256', $rawToken),
            'guest_access_expires_at' => now()->addHour(),
        ]);

        return [$booking, $rawToken];
    }
}
