<?php

namespace Tests\Feature\Routes;

use App\Models\Cinema;
use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RouteContractReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_core_named_route_contracts_exist(): void
    {
        foreach ([
            'home', 'login', 'login.store', 'register', 'register.store', 'logout',
            'cinema-context.update', 'cinemas.index', 'cinemas.show', 'showtimes.filter',
            'user.movies.index', 'user.movies.show', 'foods.index',
            'user.bookings.selectSeat', 'user.bookings.checkout', 'user.bookings.food',
            'user.bookings.food.store', 'user.bookings.review', 'user.bookings.confirm',
            'user.bookings.success', 'user.bookings.pending', 'user.bookings.failed',
            'user.bookings.history', 'user.bookings.cancel', 'user.bookings.ticket',
            'user.profile', 'user.reviews.index',
            'user.ai.recommend', 'user.ai.recommend.submit',
            'user.ai.chatbot', 'user.ai.chatbot.submit',
            'payments.vnpay.initiate', 'payments.vnpay.ipn', 'payments.vnpay.return',
            'payments.payos.initiate', 'payments.payos.webhook', 'payments.payos.return',
            'payments.payos.cancel', 'payments.payos.cancel-attempt',
            'payments.zalopay.initiate', 'payments.zalopay.callback', 'payments.zalopay.return',
            'payments.resume',
            'staff.dashboard', 'staff.tickets.index', 'staff.tickets.resolve',
            'staff.tickets.print-all', 'staff.counter.index', 'staff.sales.index',
            'staff.prints.index',
            'admin.dashboard', 'admin.reports.index', 'admin.movies.index',
            'admin.genres.index', 'admin.reviews.index',
            'admin.cinemas.index', 'admin.rooms.index',
            'admin.room-types.index', 'admin.layout-templates.index',
            'admin.showtimes.index', 'admin.pricing-rules.index', 'admin.bookings.index',
            'admin.payments.index', 'admin.discounts.index',
            'admin.foods.index', 'admin.food-orders.index', 'admin.users.index',
            'admin.roles.index', 'admin.activity-logs.index',
            'admin.payment-reconciliation.index',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Missing named route [{$name}].");
        }
    }

    public function test_public_customer_surfaces_render_without_route_contract_errors(): void
    {
        $cinema = Cinema::query()->where('code', 'CG')->first()
            ?? Cinema::factory()->primaryFpt()->create();
        $movie = Movie::query()->create([
            'title' => 'Route Contract Movie',
            'slug' => 'route-contract-movie',
            'description' => 'Movie used to verify public detail route rendering.',
            'country' => 'Việt Nam',
            'duration' => 120,
            'age_rating' => 'P',
            'release_date' => now()->toDateString(),
            'status' => Movie::STATUS_NOW_SHOWING,
        ]);

        foreach ([
            '/', '/movies', route('user.movies.show', $movie->slug), '/cinemas',
            route('cinemas.show', $cinema), '/foods', '/login', '/register',
            '/ai/recommend', '/ai/chatbot',
        ] as $uri) {
            $response = $this->get($uri);
            $this->assertSame(200, $response->getStatusCode(), "GET {$uri} did not render successfully.");
        }
    }

    public function test_staff_and_admin_dashboards_render_for_authorized_users(): void
    {
        $this->seedRbac();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('staff.dashboard'))
            ->assertOk();

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.dashboard'))
            ->assertOk();

        $customer = $this->userWithRole('user');
        $this->actingAs($customer)->get(route('user.bookings.history'))->assertOk();
        $this->actingAs($customer)->get(route('user.profile'))->assertOk();
    }
}
