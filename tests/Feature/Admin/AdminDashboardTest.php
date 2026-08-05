<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Showtime;
use App\Services\AdminDashboardService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_canonical_dashboard_route_uses_the_controller_and_current_rbac_stack(): void
    {
        $route = Route::getRoutes()->getByName('admin.dashboard');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('admin', $route->uri());
        $this->assertSame(DashboardController::class, $route->getActionName());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('active', $route->gatherMiddleware());
        $this->assertContains('permission:admin.access', $route->gatherMiddleware());
        $this->assertContains('permission:dashboard.view', $route->gatherMiddleware());
        $this->assertCount(0, collect(Route::getRoutes())->filter(
            fn ($registeredRoute): bool => $registeredRoute->uri() === 'admin/dashboard',
        ));
    }

    public function test_dashboard_access_follows_guest_customer_staff_manager_admin_and_inactive_rules(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('user'))
            ->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))
            ->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.dashboard'))->assertOk();

        $inactive = $this->userWithRole('admin', ['status' => 'inactive']);
        $this->actingAs($inactive)->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_empty_dashboard_renders_the_complete_reference_structure_in_vietnamese(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tổng quan hệ thống')
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertSee('Tổng doanh thu')
            ->assertSee('Tổng vé đã bán')
            ->assertSee('Người dùng')
            ->assertSee('Phim đang chiếu')
            ->assertSee('Suất chiếu hôm nay')
            ->assertSee('Doanh thu 7 ngày qua')
            ->assertSee('Phân tích nhanh MovieMate')
            ->assertSee('Top phim bán chạy')
            ->assertSee('Đơn đặt vé gần đây')
            ->assertSee('Chưa có doanh thu được xác minh trong 7 ngày qua.')
            ->assertSee('Chưa có dữ liệu bán vé để xếp hạng phim.')
            ->assertSee('Chưa có đơn đặt vé.')
            ->assertDontSee('Dashboard')
            ->assertDontSee('Admin Panel')
            ->assertDontSee('MovieMate AI Insights')
            ->assertDontSee('payment_status paid')
            ->assertDontSee('Ã')
            ->assertDontSee('Â');

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_revenue_and_ticket_metrics_only_count_each_verified_paid_booking_once(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 12:00:00', 'UTC'));
        $owner = $this->userWithRole('user');
        $paid = $this->paidBooking($owner->id, 'paid');
        $this->successfulPayment($paid, now()->subHour());
        $this->successfulPayment($paid, now()->subMinutes(30), 'zalopay');

        $used = $this->paidBooking($owner->id, 'used');
        $this->successfulPayment($used, now()->subDay());

        $review = $this->pendingBooking($owner->id);
        $this->payment($review, Payment::STATUS_REVIEW, now()->subHour());

        $failedPaidState = $this->paidBooking($owner->id, 'paid');
        $this->payment($failedPaidState, Payment::STATUS_FAILED, now()->subHour());

        $cancelled = $this->paidBooking($owner->id, 'cancelled');
        $this->successfulPayment($cancelled, now()->subHour());

        $data = app(AdminDashboardService::class)->overview();

        $this->assertSame(100_000, $data['metrics']['totalRevenue']);
        $this->assertSame(2, $data['metrics']['ticketsSold']);
        $this->assertSame(100_000, collect($data['revenueChart'])->sum('revenue'));
        $this->assertCount(2, $data['topMovies']);
        $this->assertSame(1, (int) $data['topMovies']->first()->tickets_sold);
        $this->assertSame(100_000, $data['topMovies']->sum(fn ($movie): int => (int) $movie->revenue));
        $this->assertSame(2, $data['topMovies']->sum(fn ($movie): int => (int) $movie->booking_count));
    }

    public function test_couple_pair_counts_two_admission_seats_but_revenue_and_booking_once(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario();
        $coupleSeatIds = $scenario['seats']->where('type', 'couple')->pluck('id')->all();
        $booking = $this->reserve($scenario, $coupleSeatIds, $owner->id)->booking;
        $booking->forceFill([
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
        ])->save();
        $this->successfulPayment($booking, now());

        $data = app(AdminDashboardService::class)->overview();

        $this->assertSame(2, $data['metrics']['ticketsSold']);
        $this->assertSame(100_000, $data['metrics']['totalRevenue']);
        $this->assertSame(2, (int) $data['topMovies']->first()->tickets_sold);
        $this->assertSame(1, (int) $data['topMovies']->first()->booking_count);
        $this->assertSame(100_000, (int) $data['topMovies']->first()->revenue);
    }

    public function test_today_showtime_metric_only_counts_active_single_cinema_operations(): void
    {
        $active = $this->bookingScenario(false);
        $active['showtime']->update(['show_date' => now()->toDateString()]);

        Showtime::query()->create([
            ...$active['showtime']->only([
                'movie_id', 'cinema_id', 'room_id', 'room_layout_id', 'show_date', 'show_time', 'price', 'vip_price',
            ]),
            'show_time' => '21:30:00',
            'status' => 'cancelled',
        ]);

        $inactive = $this->bookingScenario(false);
        $inactive['room']->update(['status' => 'inactive']);
        $inactive['showtime']->update(['show_date' => now()->toDateString()]);

        $this->assertSame(1, app(AdminDashboardService::class)->overview()['metrics']['showtimesToday']);
    }

    public function test_revenue_chart_uses_configured_timezone_boundaries(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 00:30:00', 'Asia/Ho_Chi_Minh'));
        $owner = $this->userWithRole('user');

        $today = $this->paidBooking($owner->id, 'paid');
        $todayPayment = $this->successfulPayment($today, now());
        DB::table('payments')->where('id', $todayPayment->id)->update([
            'paid_at' => '2026-08-04 17:15:00',
            'verified_at' => '2026-08-04 17:15:00',
        ]);

        $yesterday = $this->paidBooking($owner->id, 'paid');
        $yesterdayPayment = $this->successfulPayment($yesterday, now());
        DB::table('payments')->where('id', $yesterdayPayment->id)->update([
            'paid_at' => '2026-08-04 16:59:59',
            'verified_at' => '2026-08-04 16:59:59',
        ]);

        $chart = collect(app(AdminDashboardService::class)->overview()['revenueChart'])->keyBy('date');

        $this->assertSame(50_000, $chart['05/08']['revenue']);
        $this->assertSame(50_000, $chart['04/08']['revenue']);
    }

    public function test_recent_bookings_are_limited_ordered_eager_loaded_and_privacy_safe(): void
    {
        $admin = $this->userWithRole('admin');
        $scenario = $this->bookingScenario(false);
        $bookings = collect();

        foreach (range(1, 8) as $index) {
            $bookings->push($this->bookingForScenario($scenario, [
                'user_id' => null,
                'booking_code' => 'DASH-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'customer_email' => "private-{$index}@example.test",
            ]));
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = app(AdminDashboardService::class)->overview();
        $queriesBeforeRelations = count(DB::getQueryLog());
        foreach ($data['recentBookings'] as $booking) {
            $booking->user?->name;
            $booking->showtime?->movie?->title;
            $booking->showtime?->cinema?->name;
            $booking->showtime?->room?->name;
        }

        $this->assertCount(6, $data['recentBookings']);
        $this->assertSame(
            $bookings->sortByDesc('id')->take(6)->pluck('id')->values()->all(),
            $data['recentBookings']->pluck('id')->all(),
        );
        $this->assertSame($queriesBeforeRelations, count(DB::getQueryLog()));
        $this->assertLessThanOrEqual(15, $queriesBeforeRelations);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Khách đặt vé')
            ->assertSee('DASH-08')
            ->assertDontSee('DASH-01');
        foreach (range(1, 8) as $index) {
            $response->assertDontSee("private-{$index}@example.test");
        }
    }

    public function test_top_movies_are_limited_ordered_and_use_local_poster_accessor_with_fallback(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('movies/posters/dashboard.jpg', 'poster');
        $owner = $this->userWithRole('user');
        $expectedMovieId = null;

        foreach (range(1, 6) as $index) {
            $booking = $this->paidBooking($owner->id, 'paid');
            $booking->showtime->movie->update([
                'title' => "Phim dashboard {$index}",
                'poster' => $index === 1 ? 'movies/posters/dashboard.jpg' : null,
            ]);
            $this->successfulPayment($booking, now()->subMinutes($index));
            $expectedMovieId ??= $booking->showtime->movie_id;
        }

        $data = app(AdminDashboardService::class)->overview();

        $this->assertCount(5, $data['topMovies']);
        $this->assertSame($expectedMovieId, $data['topMovies']->first()->id);
        $this->assertSame('/storage/movies/posters/dashboard.jpg', $data['topMovies']->first()->poster_url);

        $response = $this->actingAs($this->userWithRole('admin'))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('/storage/movies/posters/dashboard.jpg', false)
            ->assertSee('admin-media-fallback', false)
            ->assertSee('Đã thanh toán')
            ->assertDontSee('pending_payment');
        $response->assertDontSee('https://images.unsplash.com', false);
    }

    private function pendingBooking(int $userId): Booking
    {
        $scenario = $this->bookingScenario(false);

        return $this->reserve($scenario, [$scenario['seats'][0]->id], $userId)->booking;
    }

    private function paidBooking(int $userId, string $bookingStatus): Booking
    {
        $booking = $this->pendingBooking($userId);
        $booking->forceFill([
            'payment_status' => 'paid',
            'booking_status' => $bookingStatus,
            'paid_at' => now(),
        ])->save();

        return $booking->fresh();
    }

    private function successfulPayment(Booking $booking, DateTimeInterface $paidAt, string $provider = 'vnpay'): Payment
    {
        return $this->payment($booking, Payment::STATUS_SUCCESS, $paidAt, $provider, true);
    }

    private function payment(
        Booking $booking,
        string $status,
        DateTimeInterface $paidAt,
        string $provider = 'vnpay',
        bool $verified = false,
    ): Payment {
        return Payment::createForProvider($provider, [
            'booking_id' => $booking->id,
            'payment_method' => $provider,
            'order_code' => 'DASH-'.str()->upper(str()->random(20)),
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => $status,
            'paid_at' => $paidAt,
            'verified_at' => $verified ? $paidAt : null,
            'expires_at' => now()->addMinutes(10),
            'reconcile_until' => now()->addDay(),
        ]);
    }
}
