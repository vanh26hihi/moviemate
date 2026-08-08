<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Vite;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Payments\PaymentTestCase;

class HttpsApplicationFormsTest extends PaymentTestCase
{
    private const ORIGIN = 'https://forms-tunnel.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => self::ORIGIN,
            'payment.public_hosts' => ['forms-tunnel.example.test'],
            'trustedproxy.proxies' => ['127.0.0.1'],
            'trustedproxy.hosts' => ['localhost', 'forms-tunnel.example.test'],
        ]);
        $this->app->make(Vite::class)->useHotFile(storage_path('framework/testing-vite.hot'));
    }

    public function test_customer_booking_forms_keep_the_forwarded_https_origin(): void
    {
        $scenario = $this->bookingScenario();

        $seatResponse = $this->forwardedGet(route(
            'user.bookings.selectSeat',
            $scenario['showtime'],
            false,
        ))->assertOk();
        $this->assertSafeHtml($seatResponse);
        $seatResponse->assertSee(
            'action="'.self::ORIGIN.route('user.bookings.checkout', $scenario['showtime'], false).'"',
            false,
        );

        $foodResponse = $this->forwardedGet(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ], false))->assertOk();
        $this->assertSafeHtml($foodResponse);
        $foodResponse->assertSee(
            'action="'.self::ORIGIN.route('user.bookings.food.store', absolute: false).'"',
            false,
        );

        $this->forwardedPost(route('user.bookings.food.store', absolute: false), [
            'customer_email' => 'secure-flow@example.test',
            'skip_food' => 1,
        ])->assertRedirect(self::ORIGIN.route('user.bookings.review', absolute: false));

        $reviewResponse = $this->forwardedGet(route('user.bookings.review', absolute: false))->assertOk();
        $this->assertSafeHtml($reviewResponse);
        $reviewResponse->assertSee(
            'action="'.self::ORIGIN.route('user.bookings.confirm', absolute: false).'"',
            false,
        );
    }

    public function test_public_admin_and_logout_forms_keep_the_forwarded_https_origin(): void
    {
        $movieResponse = $this->forwardedGet(route('user.movies.index', absolute: false))->assertOk();
        $this->assertSafeHtml($movieResponse);
        $movieResponse->assertSee('action="'.self::ORIGIN.'/movies"', false);

        $this->seedRbac();
        $admin = $this->userWithRole('admin');
        $adminResponse = $this->actingAs($admin)
            ->forwardedGet(route('admin.rooms.create', absolute: false))
            ->assertOk();
        $this->assertSafeHtml($adminResponse);
        $adminResponse->assertSee('action="'.self::ORIGIN.'/admin/rooms"', false);
        $adminResponse->assertSee('action="'.self::ORIGIN.'/logout"', false);
    }

    private function assertSafeHtml(TestResponse $response): void
    {
        $content = $response->getContent();

        $this->assertDoesNotMatchRegularExpression('/(?:action|src|href)=["\']http:\/\//i', $content);
        $this->assertStringNotContainsString('http://localhost', $content);
        $this->assertStringNotContainsString('http://127.0.0.1', $content);
    }

    /** @param array<string, mixed> $data */
    private function forwardedPost(string $path, array $data = []): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders($this->forwardedHeaders())
            ->post('http://upstream.internal'.$path, $data);
    }

    private function forwardedGet(string $path): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders($this->forwardedHeaders())
            ->get('http://upstream.internal'.$path);
    }

    /** @return array<string, string> */
    private function forwardedHeaders(): array
    {
        return [
            'X-Forwarded-For' => '198.51.100.80',
            'X-Forwarded-Host' => 'forms-tunnel.example.test',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Proto' => 'https',
        ];
    }
}
