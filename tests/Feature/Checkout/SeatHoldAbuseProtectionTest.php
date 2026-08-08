<?php

namespace Tests\Feature\Checkout;

use App\Services\BookingCancellationService;
use App\Services\BookingCheckoutDraftService;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class SeatHoldAbuseProtectionTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_rapid_hold_creation_is_bounded_without_mutating_booking_or_payment_state(): void
    {
        $scenario = $this->bookingScenario(false);
        $this->withoutVite();
        $this->get(route('user.bookings.checkout', $scenario['showtime']).'?selected_seats='.$scenario['seats'][0]->id)
            ->assertOk();

        foreach (range(1, 4) as $attempt) {
            $this->post(route('user.bookings.confirm'))->assertStatus(302);
        }
        $limited = $this->post(route('user.bookings.confirm'));
        $this->assertSame(429, $limited->getStatusCode(), $limited->getContent());
        $limited->assertSee('Bạn đã tạo quá nhiều lượt giữ ghế trong thời gian ngắn. Vui lòng chờ một chút rồi thử lại.');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_seats', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_one_active_hold_per_authenticated_identity_and_showtime_allows_retry_after_cancel(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $tokens = app(BookingTokenService::class);
        $firstToken = $tokens->issueCheckoutToken();
        $booking = app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            [$scenario['seats'][0]->id],
            $user->id,
            $user->email,
            $firstToken,
        )->booking;

        $request = Request::create('/booking/confirm', 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $user);
        $draft = app(BookingCheckoutDraftService::class)->start(
            $request,
            $scenario['showtime']->id,
            [$scenario['seats'][0]->id],
        );

        try {
            app(BookingCheckoutDraftService::class)->assertMayCreateHold($request, $draft);
            $this->fail('A second active hold for the same identity and showtime must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('checkout', $exception->errors());
        }

        $this->assertTrue(app(BookingCancellationService::class)->cancel($booking->id)->cancelled);
        app(BookingCheckoutDraftService::class)->assertMayCreateHold($request, $draft);
        $this->addToAssertionCount(1);
    }

    public function test_limiter_keys_are_opaque_and_keep_two_sessions_on_a_shared_network_distinct(): void
    {
        $drafts = app(BookingCheckoutDraftService::class);
        $first = $this->requestWithSession('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $second = $this->requestWithSession('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
        $drafts->start($first, 42, [1]);
        $drafts->start($second, 42, [1]);

        $firstKeys = $drafts->holdCreationRateLimitKeys($first);
        $secondKeys = $drafts->holdCreationRateLimitKeys($second);

        $this->assertNotSame($firstKeys['primary'], $secondKeys['primary']);
        $this->assertNotSame($firstKeys['session'], $secondKeys['session']);
        $this->assertSame($firstKeys['network'], $secondKeys['network']);
        foreach ([...array_values($firstKeys), ...array_values($secondKeys)] as $key) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key);
            $this->assertStringNotContainsString('203.0.113.77', $key);
        }
    }

    private function requestWithSession(string $sessionId): Request
    {
        $request = Request::create('/booking/confirm', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.77',
        ]);
        $session = new Store('moviemate-test', new ArraySessionHandler(60), $sessionId);
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }
}
