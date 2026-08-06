<?php

namespace Tests\Feature\Checkout;

use App\Models\FoodItem;
use App\Models\User;
use App\Services\BookingCheckoutDraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Payments\PaymentTestCase;

class FoodSelectionThrottleTest extends PaymentTestCase
{
    public function test_get_food_page_does_not_consume_the_food_mutation_limit(): void
    {
        $scenario = $this->bookingScenario(false);
        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->get(route('user.bookings.food'))->assertOk();
        }

        config(['booking.food_mutation_max_attempts' => 1]);
        $this->skipFood()->assertRedirect(route('user.bookings.review'));
        $this->skipFood(json: true)->assertTooManyRequests();
    }

    public function test_single_skip_and_single_confirmation_reach_the_same_review_step(): void
    {
        $skipScenario = $this->bookingScenario(false);
        $this->startFoodSelection($skipScenario);
        $this->skipFood()->assertRedirect(route('user.bookings.review'));
        $this->assertSame([], $this->draft()['food_items']);

        $confirmScenario = $this->bookingScenario(false);
        $food = FoodItem::query()->create(['name' => 'Combo confirm', 'price' => 35_000, 'active' => true]);
        $this->startFoodSelection($confirmScenario);
        $this->confirmFood($food)->assertRedirect(route('user.bookings.review'));
        $this->assertSame([
            ['food_id' => $food->id, 'quantity' => 2],
        ], $this->draft()['food_items']);
    }

    public function test_one_authenticated_users_limit_does_not_throttle_another_user(): void
    {
        config(['booking.food_mutation_max_attempts' => 2]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first);
        $this->startFoodSelection($this->bookingScenario(false));
        $this->skipFood(json: true)->assertRedirect();
        $this->skipFood(json: true)->assertRedirect();
        $this->skipFood(json: true)->assertTooManyRequests();

        $this->actingAs($second);
        $this->startFoodSelection($this->bookingScenario(false));
        $this->skipFood()->assertRedirect(route('user.bookings.review'));
    }

    public function test_one_checkout_draft_does_not_throttle_another_draft_for_the_same_user(): void
    {
        config(['booking.food_mutation_max_attempts' => 1]);
        $this->actingAs(User::factory()->create());

        $this->startFoodSelection($this->bookingScenario(false));
        $firstToken = $this->draft()['checkout_token'];
        $this->skipFood()->assertRedirect();
        $this->skipFood(json: true)->assertTooManyRequests();

        $this->startFoodSelection($this->bookingScenario(false));
        $this->assertNotSame($firstToken, $this->draft()['checkout_token']);
        $this->skipFood()->assertRedirect(route('user.bookings.review'));
    }

    public function test_guest_and_authenticated_checkout_scopes_are_isolated(): void
    {
        config(['booking.food_mutation_max_attempts' => 1]);

        $this->startFoodSelection($this->bookingScenario(false));
        $this->skipFood()->assertRedirect();
        $this->skipFood(json: true)->assertTooManyRequests();

        $this->actingAs(User::factory()->create());
        $this->startFoodSelection($this->bookingScenario(false));
        $this->skipFood()->assertRedirect(route('user.bookings.review'));
    }

    public function test_forwarded_requests_with_the_same_client_ip_are_scoped_by_checkout_identity(): void
    {
        config([
            'booking.food_mutation_max_attempts' => 1,
            'trustedproxy.proxies' => ['127.0.0.1'],
            'trustedproxy.hosts' => ['checkout-tunnel.example.test'],
        ]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first);
        $this->forwardedStartFoodSelection($this->bookingScenario(false));
        $this->forwardedSkipFood()->assertRedirect();
        $this->forwardedSkipFood(json: true)->assertTooManyRequests();

        $this->actingAs($second);
        $this->forwardedStartFoodSelection($this->bookingScenario(false));
        $this->forwardedSkipFood()->assertRedirect('https://checkout-tunnel.example.test/booking/review');
    }

    public function test_throttled_json_mutation_returns_429_without_changing_checkout_or_business_rows(): void
    {
        config(['booking.food_mutation_max_attempts' => 1]);
        $food = FoodItem::query()->create(['name' => 'Immutable combo', 'price' => 40_000, 'active' => true]);
        $this->startFoodSelection($this->bookingScenario(false));
        $this->confirmFood($food)->assertRedirect();
        $draftBefore = $this->draft();
        $countsBefore = $this->businessCounts();

        $response = $this->skipFood(json: true)
            ->assertTooManyRequests()
            ->assertJson(['message' => 'Bạn thao tác quá nhanh. Vui lòng chờ vài giây rồi thử lại.']);

        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertSame($draftBefore, $this->draft());
        $this->assertSame($countsBefore, $this->businessCounts());
    }

    public function test_browser_throttle_redirects_back_with_retry_after_and_friendly_message(): void
    {
        config(['booking.food_mutation_max_attempts' => 1]);
        $this->startFoodSelection($this->bookingScenario(false));
        $this->skipFood()->assertRedirect(route('user.bookings.review'));

        $response = $this->skipFood()
            ->assertRedirect(route('user.bookings.food'))
            ->assertSessionHas('warning', 'Bạn thao tác quá nhanh. Vui lòng chờ vài giây rồi thử lại.');

        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Bạn thao tác quá nhanh. Vui lòng chờ vài giây rồi thử lại.');
    }

    public function test_repeated_skip_is_session_idempotent_and_creates_no_business_rows(): void
    {
        config(['booking.food_mutation_max_attempts' => 3]);
        $this->startFoodSelection($this->bookingScenario(false));

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->skipFood()->assertRedirect(route('user.bookings.review'));
        }

        $this->assertSame([], $this->draft()['food_items']);
        $this->assertSame([
            'bookings' => 0,
            'booking_seats' => 0,
            'orders' => 0,
            'order_items' => 0,
            'payments' => 0,
        ], $this->businessCounts());
    }

    public function test_rendered_controls_and_submit_guard_have_one_submission_contract(): void
    {
        $middleware = Route::getRoutes()->getByName('user.bookings.food.store')->gatherMiddleware();
        $this->assertContains('throttle:booking-food-mutation', $middleware);
        $this->assertNotContains('throttle:20,1', $middleware);

        $response = $this->startFoodSelection($this->bookingScenario(false));
        $response->assertSee('name="checkout_action" value="confirm_food"', false)
            ->assertDontSee('name="checkout_action" value="skip_food"', false)
            ->assertDontSee('name="skip_food"', false)
            ->assertDontSee('Bỏ qua đồ ăn')
            ->assertSee('Tiếp tục thanh toán')
            ->assertSee('Quay lại chọn ghế')
            ->assertDontSee('formaction=', false)
            ->assertDontSee('onclick=', false);

        $javascript = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($javascript);
        $guardStart = strpos($javascript, 'function initializeSubmitGuards()');
        $guardEnd = strpos($javascript, 'function initializeCountdowns()');
        $this->assertIsInt($guardStart);
        $this->assertIsInt($guardEnd);
        $guard = substr($javascript, $guardStart, $guardEnd - $guardStart);

        $this->assertStringContainsString('submitGuardInitialized', $guard);
        $this->assertStringContainsString("form.addEventListener('submit'", $guard);
        $this->assertStringContainsString('submittedValue.value = submitter.value', $guard);
        $this->assertStringContainsString('button.disabled = true', $guard);
        $this->assertStringContainsString("window.addEventListener('pageshow'", $guard);
        $this->assertStringNotContainsString('requestSubmit(', $guard);
        $this->assertStringNotContainsString('.submit()', $guard);
        $this->assertStringNotContainsString('fetch(', $guard);
    }

    public function test_limiter_key_is_opaque_and_contains_no_checkout_or_session_secret(): void
    {
        $this->startFoodSelection($this->bookingScenario(false));
        $draft = $this->draft();
        $request = Request::create('/booking/food', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $key = app(BookingCheckoutDraftService::class)->foodMutationRateLimitKey($request);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $key);
        $this->assertStringNotContainsString($draft['checkout_token'], $key);
        $this->assertStringNotContainsString($this->app['session.store']->getId(), $key);
        $this->assertStringNotContainsString($draft['actor_identity'], $key);
    }

    private function startFoodSelection(array $scenario): TestResponse
    {
        return $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();
    }

    private function skipFood(bool $json = false): TestResponse
    {
        $method = $json ? 'postJson' : 'post';

        return $this->{$method}(route('user.bookings.food.store'), [
            'customer_email' => 'skip@example.test',
            'checkout_action' => 'skip_food',
        ]);
    }

    private function confirmFood(FoodItem $food): TestResponse
    {
        return $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'confirm@example.test',
            'checkout_action' => 'confirm_food',
            'food_items' => [['food_id' => $food->id, 'quantity' => 2]],
        ]);
    }

    private function forwardedStartFoodSelection(array $scenario): TestResponse
    {
        $path = route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ], false);

        return $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders($this->forwardedHeaders())
            ->get('http://upstream.internal'.$path)
            ->assertOk();
    }

    private function forwardedSkipFood(bool $json = false): TestResponse
    {
        $method = $json ? 'postJson' : 'post';

        return $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders($this->forwardedHeaders())
            ->{$method}('http://upstream.internal'.route('user.bookings.food.store', absolute: false), [
                'customer_email' => 'forwarded@example.test',
                'checkout_action' => 'skip_food',
            ]);
    }

    /** @return array<string, string> */
    private function forwardedHeaders(): array
    {
        return [
            'X-Forwarded-For' => '198.51.100.90',
            'X-Forwarded-Host' => 'checkout-tunnel.example.test',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Proto' => 'https',
        ];
    }

    /** @return array<string, mixed> */
    private function draft(): array
    {
        return $this->app['session.store']->get('booking_checkout_draft');
    }

    /** @return array<string, int> */
    private function businessCounts(): array
    {
        return collect(['bookings', 'booking_seats', 'orders', 'order_items', 'payments'])
            ->mapWithKeys(fn (string $table): array => [$table => $this->app['db']->table($table)->count()])
            ->all();
    }
}
