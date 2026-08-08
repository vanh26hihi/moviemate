<?php

namespace Tests\Feature\Checkout;

use App\Http\Controllers\User\BookingController;
use App\Http\Controllers\User\RetiredBookingStoreController;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Payments\PaymentTestCase;

class LegacyBookingStoreRetirementTest extends PaymentTestCase
{
    public function test_guest_and_authenticated_legacy_posts_return_gone_without_writes(): void
    {
        $payload = $this->forgedLegacyPayload();
        $countsBefore = $this->rawMutationCounts();

        $this->post('/booking/store', $payload)->assertGone();

        $this->seedRbac();
        $this->actingAs($this->userWithRole('user'))
            ->post(route('user.bookings.store'), $payload)
            ->assertGone();

        $this->assertSame($countsBefore, $this->rawMutationCounts());
    }

    public function test_production_equivalent_csrf_allows_guest_posts_without_or_with_invalid_tokens(): void
    {
        $countsBefore = $this->rawMutationCounts();

        $this->assertSame(410, $this->postThroughProductionCsrf('/booking/store')->getStatusCode());
        $this->assertSame(
            410,
            $this->postThroughProductionCsrf('/booking/store', [
                '_token' => 'invalid-csrf-token',
                ...$this->forgedLegacyPayload(),
            ])->getStatusCode()
        );

        $this->assertSame($countsBefore, $this->rawMutationCounts());
    }

    public function test_valid_csrf_forged_payload_and_30_rapid_replays_are_always_gone_without_writes(): void
    {
        $csrfToken = 'valid-test-csrf-token';
        $payload = ['_token' => $csrfToken, ...$this->forgedLegacyPayload()];
        $countsBefore = $this->rawMutationCounts();

        $this->withSession(['_token' => $csrfToken])->post('/booking/store', $payload)->assertGone();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
                ->post('/booking/store', $this->forgedLegacyPayload())
                ->assertGone();
        }

        $this->assertSame($countsBefore, $this->rawMutationCounts());
    }

    public function test_only_dependency_free_retired_responder_handles_the_legacy_uri_without_throttle(): void
    {
        $legacyRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === 'booking/store' && in_array('POST', $route->methods(), true));

        $this->assertCount(1, $legacyRoutes);
        $legacyRoute = $legacyRoutes->sole();
        $this->assertSame('user.bookings.store', $legacyRoute->getName());
        $this->assertSame(RetiredBookingStoreController::class, $legacyRoute->getActionName());
        $this->assertNotContains('throttle:10,1', $legacyRoute->gatherMiddleware());
        $this->assertFalse(collect($legacyRoute->gatherMiddleware())->contains(
            fn (string $middleware): bool => str_contains(strtolower($middleware), 'throttle')
        ));

        $controller = new ReflectionClass(RetiredBookingStoreController::class);
        $this->assertNull($controller->getConstructor());
        $this->assertSame(0, $controller->getMethod('__invoke')->getNumberOfParameters());
        $this->assertFalse(method_exists(BookingController::class, 'store'));
    }

    public function test_csrf_exceptions_are_exact_and_other_booking_mutations_remain_protected(): void
    {
        $middleware = $this->productionCsrfMiddleware();

        $this->assertSame([
            'payments/zalopay/callback',
            'payments/payos/webhook',
            'booking/store',
        ], $middleware->getExcludedPaths());

        $callback = $this->requestWithSession('/payments/zalopay/callback');
        $this->assertSame(204, $middleware->handle($callback, fn (): Response => response('', 204))->getStatusCode());

        $payOsWebhook = $this->requestWithSession('/payments/payos/webhook');
        $this->assertSame(204, $middleware->handle($payOsWebhook, fn (): Response => response('', 204))->getStatusCode());

        foreach (['/booking/confirm', '/booking/food', '/booking/store/anything'] as $uri) {
            try {
                $middleware->handle(
                    $this->requestWithSession($uri, ['_token' => 'invalid-csrf-token']),
                    fn (): Response => response('', 204)
                );
                $this->fail("Expected CSRF rejection for {$uri}.");
            } catch (TokenMismatchException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_unified_checkout_and_history_ticket_staff_routes_remain_available(): void
    {
        foreach ([
            'user.bookings.checkout',
            'user.bookings.food',
            'user.bookings.food.store',
            'user.bookings.review',
            'user.bookings.confirm',
            'user.bookings.history',
            'user.bookings.ticket',
            'staff.tickets.index',
            'staff.tickets.check',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Missing route {$routeName}");
        }
    }

    private function forgedLegacyPayload(): array
    {
        return [
            'showtime_id' => 1,
            'seat_ids' => [1, 2],
            'customer_email' => 'attacker@example.test',
            'checkout_token' => 'checkout-00000000000000000000000000000000',
            'total_amount' => 1,
            'seat_subtotal' => 1,
            'food_subtotal' => 1,
            'payment_status' => 'paid',
            'food_items' => [['food_id' => 1, 'quantity' => 99, 'price' => 1]],
        ];
    }

    /** @return array<string, int> */
    private function rawMutationCounts(): array
    {
        return collect([
            'bookings',
            'booking_seats',
            'payments',
            'orders',
            'order_items',
            'booking_ticket_deliveries',
        ])->mapWithKeys(fn (string $table): array => [
            $table => DB::table($table)->count(),
        ])->all();
    }

    private function postThroughProductionCsrf(string $uri, array $payload = []): Response
    {
        return $this->productionCsrfMiddleware()->handle(
            $this->requestWithSession($uri, $payload),
            fn (Request $request): Response => $this->app->make(HttpKernel::class)->handle($request)
        );
    }

    private function requestWithSession(string $uri, array $payload = []): Request
    {
        $request = Request::create($uri, 'POST', $payload, server: [
            'REMOTE_ADDR' => '203.0.113.25',
        ]);
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    private function productionCsrfMiddleware(): PreventRequestForgery
    {
        return new ProductionEquivalentPreventRequestForgery($this->app, $this->app['encrypter']);
    }
}

final class ProductionEquivalentPreventRequestForgery extends PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false;
    }
}
