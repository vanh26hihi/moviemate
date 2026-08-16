<?php

namespace Tests\Feature\Reports;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\BookingTicketPrint;
use App\Models\Cinema;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\CinemaAccessService;
use App\Services\Reports\AdminReportingService;
use App\Services\Reports\ReportScopeFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ReportingR9Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 12:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_authoritative_revenue_ticket_units_and_finance_time_are_exact(): void
    {
        $fixture = $this->fixture();
        $report = $this->report($fixture['admin']);

        $this->assertSame(380_000, $report['summary']['revenue']);
        $this->assertSame(4, $report['summary']['paidBookings']);
        $this->assertSame(4, $report['summary']['logicalTickets']);
        $this->assertSame(5, $report['summary']['physicalSeats']);
        $this->assertSame(310_000, $this->report($fixture['admin'], ['cinema' => (string) $fixture['primary']->id])['summary']['revenue']);
        $this->assertSame(70_000, $this->report($fixture['admin'], ['cinema' => (string) $fixture['other']->id])['summary']['revenue']);

        $series = collect($report['revenueSeries'])->keyBy('date');
        $this->assertSame(100_000, $series['2026-08-03']['revenue']);
        $this->assertSame(120_000, $series['2026-08-04']['revenue']);
        $this->assertSame(70_000, $series['2026-08-05']['revenue']);
        $this->assertSame(90_000, $series['2026-08-06']['revenue']);
        $this->assertSame('Report Movie A', $report['todayShowtimes'][0]['movie']);
        $this->assertSame('18:00', $report['todayShowtimes'][0]['start']->format('H:i'));
        $this->assertSame('20:00', $report['todayShowtimes'][0]['end']->format('H:i'));

        foreach ([Payment::STATUS_PENDING, Payment::STATUS_PROCESSING, Payment::STATUS_UNRESOLVED, Payment::STATUS_REVIEW, Payment::STATUS_FAILED, Payment::STATUS_EXPIRED] as $status) {
            $this->assertDatabaseHas('payments', ['status' => $status]);
        }
    }

    public function test_top_movies_peak_times_and_multi_genre_semantics_do_not_multiply_money(): void
    {
        $fixture = $this->fixture();
        $report = $this->report($fixture['admin']);

        $this->assertSame(['Report Movie A', 'Archived Report Movie', 'Report Movie C'], collect($report['topMovies'])->pluck('title')->all());
        $this->assertSame([190_000, 120_000, 70_000], collect($report['topMovies'])->pluck('revenue')->all());
        $this->assertSame('archived', $report['topMovies'][1]['status']);

        $peaks = collect($report['peakTimes'])->keyBy('key');
        $this->assertSame(1, $peaks['morning']['logical_tickets']);
        $this->assertSame(1, $peaks['afternoon']['logical_tickets']);
        $this->assertSame(1, $peaks['evening']['logical_tickets']);
        $this->assertSame(1, $peaks['late']['logical_tickets']);
        $this->assertSame(2, $peaks['late']['physical_seats']);

        $genres = collect($report['genres'])->keyBy('name');
        $this->assertSame(3, $genres['Action']['logical_tickets']);
        $this->assertSame(3, $genres['Drama']['logical_tickets']);
        $this->assertSame(4, $report['summary']['logicalTickets']);
        $this->assertSame(['Report Movie A', 'Report Movie C'], collect($report['currentMovies'])->pluck('title')->all());
    }

    public function test_channel_provider_and_distinct_counter_actor_breakdowns_are_authoritative(): void
    {
        $fixture = $this->fixture();
        $report = $this->report($fixture['admin']);
        $channels = collect($report['salesChannels'])->keyBy('key');
        $providers = collect($report['paymentMethods'])->keyBy('key');

        $this->assertSame(3, $channels['online']['bookings']);
        $this->assertSame(290_000, $channels['online']['revenue']);
        $this->assertSame(1, $channels['counter']['bookings']);
        $this->assertSame(90_000, $channels['counter']['revenue']);
        foreach (['vnpay' => 100_000, 'payos' => 120_000, 'zalopay' => 70_000, 'counter_cash' => 90_000] as $provider => $revenue) {
            $this->assertSame(1, $providers[$provider]['transactions']);
            $this->assertSame($revenue, $providers[$provider]['revenue']);
        }

        $this->assertSame($fixture['creator']->name, $report['counterCreators'][0]['name']);
        $this->assertSame($fixture['settler']->name, $report['counterSettlers'][0]['name']);
        $this->assertNotSame($report['counterCreators'][0]['actor_id'], $report['counterSettlers'][0]['actor_id']);
        $this->assertArrayNotHasKey('email', $report['counterCreators'][0]);
        $this->assertArrayNotHasKey('email', $report['counterSettlers'][0]);
        $this->assertSame('inactive', $fixture['creator']->fresh()->status);
        $this->assertSame(90_000, $this->report($fixture['admin'], ['provider' => 'counter_cash'])['summary']['revenue']);
        $this->assertSame(290_000, $this->report($fixture['admin'], ['sales_channel' => 'online'])['summary']['revenue']);
    }

    public function test_print_operations_use_current_and_append_only_evidence(): void
    {
        $fixture = $this->fixture();
        $operations = $this->report($fixture['admin'])['ticketOperations'];

        $this->assertSame(4, $operations['eligible']);
        $this->assertSame(1, $operations['printed']);
        $this->assertSame(1, $operations['printFailed']);
        $this->assertSame(1, $operations['printWaiting']);
        $this->assertSame(1, $operations['unprinted']);
        $this->assertSame(['unresolved' => 1, 'review' => 1, 'total' => 2], $this->report($fixture['admin'])['attention']);
    }

    public function test_reports_enforce_branch_authorization_and_direct_route_permissions(): void
    {
        $fixture = $this->fixture();
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('user');

        $this->actingAs($fixture['admin'])->get(route('admin.reports.index', $this->filters()))
            ->assertOk()->assertSee('380.000 ₫');
        $this->get(route('admin.reports.index', [...$this->filters(), 'cinema' => $fixture['other']->id]))
            ->assertOk()->assertSee('70.000 ₫');

        $this->actingAs($manager)->get(route('admin.reports.index', $this->filters()))
            ->assertOk()->assertSee('310.000 ₫')->assertDontSee('70.000 ₫');
        $this->get(route('admin.reports.index', [...$this->filters(), 'cinema' => $fixture['other']->id]))
            ->assertForbidden();
        $this->actingAs($staff)->get(route('admin.reports.index', $this->filters()))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.reports.index', $this->filters()))->assertForbidden();
        $this->get(route('admin.reports.index', $this->filters()))->assertForbidden();
    }

    public function test_report_csv_export_is_authoritative_scoped_and_privacy_safe(): void
    {
        $fixture = $this->fixture();

        $response = $this->actingAs($fixture['admin'])->get(route('admin.reports.export', $this->filters()));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('cache-control', 'max-age=0, no-store, private')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-disposition');
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertSame(4, substr_count($content, 'Report Movie'));
        $this->assertStringContainsString('Report Movie A', $content);
        $this->assertStringContainsString('100000', $content);
        $this->assertStringContainsString('120000', $content);
        $this->assertStringContainsString('90000', $content);
        $this->assertStringContainsString('70000', $content);
        $this->assertStringNotContainsString('R9-SECRET-TRANSACTION', $content);
        $this->assertStringNotContainsString('private-customer@example.test', $content);
        $this->assertStringNotContainsString('failed', $content);

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.reports.export', [...$this->filters(), 'cinema' => $fixture['other']->id]))
            ->assertForbidden();
    }

    public function test_report_filters_are_bounded_allowlisted_and_shareable(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.reports.index', ['from' => '2026-08-07', 'to' => '2026-08-01']))
            ->assertSessionHasErrors('to');
        $this->get(route('admin.reports.index', ['from' => '2025-01-01', 'to' => '2026-08-07']))
            ->assertSessionHasErrors('to');
        $this->get(route('admin.reports.index', ['provider' => 'fake-success']))
            ->assertSessionHasErrors('provider');
        $this->get(route('admin.reports.index', ['sales_channel' => 'cash-ish']))
            ->assertSessionHasErrors('sales_channel');
        $this->get(route('admin.reports.index', $this->filters()))
            ->assertOk()->assertSee('name="from" value="2026-08-01"', false)
            ->assertSee('name="to" value="2026-08-07"', false);
    }

    public function test_dashboard_is_operational_report_is_analytical_and_both_are_privacy_safe_and_query_bounded(): void
    {
        $fixture = $this->fixture();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($fixture['admin'])->get(route('admin.dashboard', $this->filters()))
            ->assertOk()
            ->assertSee('Trung tâm điều hành hôm nay')
            ->assertSee('Report Movie A')
            ->assertSee('Việc cần xử lý')
            ->assertDontSee('380.000 ₫')
            ->assertDontSee('Top phim')
            ->assertDontSee('Khung giờ cao điểm')
            ->assertDontSee('Thể loại được quan tâm')
            ->assertDontSee('Bộ lọc báo cáo')
            ->assertDontSee('private-customer@example.test')
            ->assertDontSee('R9-SECRET-TRANSACTION')
            ->assertDontSee('ticket_email_token');
        $dashboardQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(30, $dashboardQueries);

        DB::flushQueryLog();
        $this->get(route('admin.reports.index', $this->filters()))
            ->assertOk()->assertSee('Đơn tạo tại quầy theo nhân viên')->assertSee('Thu tiền tại quầy theo nhân viên');
        $reportQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(30, $reportQueries);

        DB::flushQueryLog();
        $this->withSession([CinemaAccessService::SESSION_KEY => $fixture['primary']->id])
            ->get(route('admin.dashboard'))->assertOk()->assertSee($fixture['primary']->name);
        $branchDashboardQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(30, $branchDashboardQueries);

        $scope = app(ReportScopeFactory::class)->forUser($fixture['admin'], $this->filters());
        DB::flushQueryLog();
        app(AdminReportingService::class)->revenueSeries($scope);
        $revenueQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(3, $revenueQueries);
        DB::flushQueryLog();
        app(AdminReportingService::class)->topMovies($scope);
        $topMovieQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(3, $topMovieQueries);
        DB::flushQueryLog();
        app(AdminReportingService::class)->todayShowtimes($scope);
        $todayQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(3, $todayQueries);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertDatabaseCount('activity_logs', 0);
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $admin = $this->userWithRole('admin');
        $creator = $this->userWithRole('staff', ['name' => 'Counter Creator', 'email' => 'creator-private@example.test']);
        $settler = $this->userWithRole('staff', ['name' => 'Cash Settler', 'email' => 'settler-private@example.test']);
        $printer = $this->userWithRole('staff', ['name' => 'Ticket Printer']);
        $checker = $this->userWithRole('staff', ['name' => 'Ticket Checker']);
        $primary = Cinema::query()->active()->primary()->firstOrFail();
        $primary->update(['code' => 'CG', 'name' => 'Chi nhánh Cầu Giấy', 'timezone' => 'Asia/Ho_Chi_Minh']);
        $other = Cinema::factory()->create([
            'code' => 'HD', 'name' => 'Chi nhánh Hà Đông', 'timezone' => 'Asia/Ho_Chi_Minh',
            'status' => 'inactive', 'archived_at' => now(),
        ]);

        $action = Genre::query()->create(['name' => 'Action', 'slug' => 'report-action']);
        $drama = Genre::query()->create(['name' => 'Drama', 'slug' => 'report-drama']);
        $movieA = Movie::query()->create(['title' => 'Report Movie A', 'slug' => 'report-movie-a', 'duration' => 120, 'status' => 'now_showing']);
        $movieA->genres()->attach([$action->id, $drama->id]);
        $movieB = Movie::query()->create(['title' => 'Archived Report Movie', 'slug' => 'archived-report-movie', 'duration' => 120, 'status' => 'archived']);
        $movieB->genres()->attach($drama->id);
        $movieC = Movie::query()->create(['title' => 'Report Movie C', 'slug' => 'report-movie-c', 'duration' => 90, 'status' => 'now_showing']);
        $movieC->genres()->attach($action->id);

        $normal = $this->showtime($primary, $movieA, '2026-08-03', '09:00:00', ['normal']);
        $couple = $this->showtime($primary, $movieB, '2026-08-04', '23:30:00', ['couple', 'couple']);
        $counterVip = $this->showtime($primary, $movieA, '2026-08-07', '18:00:00', ['vip']);
        $otherNormal = $this->showtime($other, $movieC, '2026-08-05', '14:00:00', ['normal']);

        $vnpay = $this->booking($normal, [80_000], 100_000);
        $vnpay->forceFill(['customer_email' => 'private-customer@example.test', 'food_subtotal' => 20_000])->save();
        $firstVnpay = $this->onlinePayment($vnpay, 'vnpay', 100_000, '2026-08-03 03:00:00');
        $firstVnpay->forceFill(['paid_at' => CarbonImmutable::parse('2025-01-01 00:00:00', 'UTC')])->save();
        $this->onlinePayment($vnpay, 'vnpay', 999_999, '2026-08-03 04:00:00');
        $this->paymentAttempt($vnpay, Payment::STATUS_FAILED);

        $payos = $this->booking($couple, [60_000, 60_000], 120_000, 'couple:C1');
        $this->onlinePayment($payos, 'payos', 120_000, '2026-08-04 04:00:00');

        $counter = $this->booking($counterVip, [90_000], 90_000, null, Booking::SALES_CHANNEL_COUNTER, $creator);
        $cash = $this->cashPayment($counter, $settler, 90_000, '2026-08-06 04:00:00');
        $cash->forceFill(['paid_at' => CarbonImmutable::parse('2025-01-01 00:00:00', 'UTC')])->save();
        $creator->forceFill(['status' => 'inactive'])->save();

        $zalopay = $this->booking($otherNormal, [70_000], 70_000);
        $this->onlinePayment($zalopay, 'zalopay', 70_000, '2026-08-05 04:00:00');

        foreach ([Payment::STATUS_PENDING, Payment::STATUS_PROCESSING, Payment::STATUS_UNRESOLVED, Payment::STATUS_REVIEW, Payment::STATUS_EXPIRED] as $status) {
            $draft = $this->booking($normal, [50_000], 50_000, null, Booking::SALES_CHANNEL_ONLINE, null, false);
            $this->paymentAttempt($draft, $status);
        }
        $cancelled = $this->booking($normal, [50_000], 50_000, null, Booking::SALES_CHANNEL_ONLINE, null, false);
        $cancelled->forceFill(['booking_status' => 'cancelled', 'payment_status' => 'unpaid'])->save();

        BookingTicketPrint::query()->create(['admission_ticket_id' => $vnpay->admissionTickets()->firstOrFail()->id, 'booking_id' => $vnpay->id, 'status' => 'printed', 'attempts_count' => 1, 'printed_by_user_id' => $printer->id, 'printed_at' => now()]);
        BookingTicketPrint::query()->create(['admission_ticket_id' => $payos->admissionTickets()->firstOrFail()->id, 'booking_id' => $payos->id, 'status' => 'retry_allowed', 'attempts_count' => 1, 'last_failed_by_user_id' => $printer->id, 'last_failed_at' => now()]);
        BookingTicketPrint::query()->create(['admission_ticket_id' => $zalopay->admissionTickets()->firstOrFail()->id, 'booking_id' => $zalopay->id, 'status' => 'retry_authorized', 'attempts_count' => 1, 'retry_authorized_by_user_id' => $admin->id, 'retry_authorized_at' => now()]);

        return compact('admin', 'creator', 'settler', 'printer', 'checker', 'primary', 'other');
    }

    private function showtime(Cinema $cinema, Movie $movie, string $date, string $time, array $seatTypes): array
    {
        $room = Room::query()->create(['cinema_id' => $cinema->id, 'code' => 'R'.str()->upper(str()->random(7)), 'name' => 'Report Room', 'room_type' => '2D', 'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active']);
        $layout = RoomLayout::query()->create(['room_id' => $room->id, 'version' => 1, 'name' => 'Report layout', 'rows' => 1, 'columns' => count($seatTypes), 'status' => 'draft']);
        $seats = collect();
        foreach ($seatTypes as $index => $type) {
            $seat = Seat::query()->create([
                'room_id' => $room->id, 'row' => 'A', 'number' => $index + 1, 'seat_code' => 'A'.($index + 1),
                'type' => $type, 'status' => 'active',
                'pair_code' => $type === 'couple' ? 'C1' : null,
                'pair_position' => $type === 'couple' ? ($index === 0 ? 'left' : 'right') : null,
            ]);
            $seats->push($seat);
            RoomLayoutCell::query()->create(['room_layout_id' => $layout->id, 'x_position' => $index + 1, 'y_position' => 1, 'cell_type' => 'seat', 'seat_id' => $seat->id]);
        }
        $layout->update(['status' => 'published', 'published_at' => now()]);
        $showtime = Showtime::query()->create(['movie_id' => $movie->id, 'cinema_id' => $cinema->id, 'room_id' => $room->id, 'room_layout_id' => $layout->id, 'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id, 'show_date' => $date, 'show_time' => $time, 'price' => 50_000, 'vip_price' => 70_000, 'pricing_version' => 'report-v1', 'status' => 'active']);

        return compact('showtime', 'seats');
    }

    private function booking(array $scenario, array $prices, int $total, ?string $sharedUnit = null, string $channel = Booking::SALES_CHANNEL_ONLINE, ?User $creator = null, bool $paid = true): Booking
    {
        $booking = new Booking;
        $booking->forceFill([
            'showtime_id' => $scenario['showtime']->id, 'booking_code' => 'R9-'.str()->upper(str()->random(14)),
            'sales_channel' => $channel, 'created_by_staff_id' => $creator?->id,
            'customer_name' => 'Private Customer', 'customer_phone' => '0900000000',
            'total_amount' => $total, 'seat_subtotal' => array_sum($prices), 'food_subtotal' => $total - array_sum($prices),
            'currency' => 'VND', 'payment_status' => $paid ? 'paid' : 'unpaid', 'booking_status' => $paid ? 'paid' : 'pending_payment',
            'paid_at' => $paid ? now() : null, 'expires_at' => now()->addHour(),
        ])->save();
        foreach ($prices as $index => $price) {
            BookingSeat::query()->create([
                'booking_id' => $booking->id, 'showtime_id' => $scenario['showtime']->id, 'seat_id' => $scenario['seats'][$index]->id,
                'active_lock_key' => $paid ? BookingSeat::ACTIVE_LOCK_KEY : null, 'price' => $price,
                'pricing_unit_key' => $sharedUnit ?? 'seat:'.$scenario['seats'][$index]->id,
                'pricing_unit_label' => $sharedUnit ? 'Ghế đôi A1/A2' : $scenario['seats'][$index]->seat_code,
                'seat_type_snapshot' => $scenario['seats'][$index]->type, 'final_unit_amount' => $total,
            ]);
        }

        return $booking->fresh();
    }

    private function onlinePayment(Booking $booking, string $provider, int $amount, string $utc): Payment
    {
        return Payment::createForProvider($provider, [
            'booking_id' => $booking->id, 'payment_method' => $provider, 'order_code' => 'R9-'.str()->upper(str()->random(18)),
            'amount' => $amount, 'currency' => 'VND', 'status' => Payment::STATUS_SUCCESS,
            'verified_at' => CarbonImmutable::parse($utc, 'UTC'), 'paid_at' => CarbonImmutable::parse($utc, 'UTC'),
            'transaction_code' => 'R9-SECRET-TRANSACTION',
        ]);
    }

    private function cashPayment(Booking $booking, User $settler, int $amount, string $utc): Payment
    {
        $payment = new Payment;
        $payment->forceFill([
            'booking_id' => $booking->id, 'provider' => Payment::PROVIDER_COUNTER_CASH,
            'payment_method' => Payment::PROVIDER_COUNTER_CASH, 'amount' => $amount, 'currency' => 'VND',
            'status' => Payment::STATUS_SUCCESS, 'settled_by_user_id' => $settler->id,
            'settled_at' => CarbonImmutable::parse($utc, 'UTC'), 'paid_at' => CarbonImmutable::parse($utc, 'UTC'),
        ])->save();

        return $payment;
    }

    private function paymentAttempt(Booking $booking, string $status): Payment
    {
        return Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id, 'payment_method' => 'vnpay', 'order_code' => 'R9-'.str()->upper(str()->random(18)),
            'amount' => (int) $booking->total_amount, 'currency' => 'VND', 'status' => $status,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function report(User $user, array $overrides = []): array
    {
        $this->actingAs($user);
        $scope = app(ReportScopeFactory::class)->forUser($user, [...$this->filters(), ...$overrides]);

        return app(AdminReportingService::class)->report($scope);
    }

    /** @return array<string, string> */
    private function filters(): array
    {
        return ['from' => '2026-08-01', 'to' => '2026-08-07', 'cinema' => 'all'];
    }
}
