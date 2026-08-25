<?php

namespace Tests\Feature\Showtimes;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\PriceBookVersion;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\SeatType;
use App\Models\Showtime;
use App\Models\ShowtimeTicketPrice;
use App\Services\CinemaAccessService;
use App\Services\PriceBookVersionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ShowtimeOperationalWorkspaceTest extends ShowtimeTestCase
{
    protected bool $prepareSingleShowtimeFormats = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_list_uses_plain_filters_responsive_views_and_helpful_empty_state(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 08:00:00', 'Asia/Ho_Chi_Minh'));

        try {
            $movie = $this->movie(110, [
                'title' => 'A deliberately long movie title that must remain readable in the operational workspace',
            ]);
            $showtime = $this->existing($movie, $this->rooms['P01'], [
                'show_date' => '2030-06-10',
                'show_time' => '09:00:00',
            ]);
            $admin = $this->userWithRole('admin');

            $this->actingAs($admin)->get(route('admin.showtimes.index', [
                'show_date' => '2030-06-10',
                'movie_id' => $movie->id,
            ]))
                ->assertOk()
                ->assertSee('for="showtime-show-date"', false)
                ->assertSee('for="showtime-movie"', false)
                ->assertSee('for="showtime-lifecycle"', false)
                ->assertSee('2 bộ lọc đang dùng')
                ->assertSee('Xóa bộ lọc')
                ->assertSee('data-showtime-desktop-list', false)
                ->assertSee('data-showtime-mobile-list', false)
                ->assertSee('data-showtime-card', false)
                ->assertSee($movie->title)
                ->assertSee('Phòng sẵn sàng')
                ->assertSee('Xem chi tiết')
                ->assertSee('Chỉnh sửa')
                ->assertSee(route('admin.showtimes.show', $showtime), false);

            $this->get(route('admin.showtimes.index', ['show_date' => '1900-01-01']))
                ->assertOk()
                ->assertSee('Không tìm thấy suất chiếu phù hợp.')
                ->assertSee('Hãy thử đổi ngày, chọn phim khác hoặc xóa bớt bộ lọc.')
                ->assertSee('Xóa bộ lọc')
                ->assertSee('Thêm suất chiếu');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_canonical_detail_route_enforces_admin_manager_staff_and_guest_authority(): void
    {
        $showtime = $this->existing($this->movie(), $this->rooms['P01']);
        $foreign = $this->foreignShowtime();

        $this->get(route('admin.showtimes.show', $showtime))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('staff'))
            ->get(route('admin.showtimes.show', $showtime))->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))
            ->withSession([CinemaAccessService::SESSION_KEY => 'all'])
            ->get(route('admin.showtimes.show', $foreign))->assertOk();

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('admin.showtimes.show', $showtime))->assertOk();
        $this->get(route('admin.showtimes.show', $foreign))->assertNotFound();
    }

    public function test_detail_uses_pinned_layout_explicit_format_and_frozen_dynamic_price_snapshots(): void
    {
        $movie = $this->movie(105, ['title' => 'Frozen Operational Movie', 'age_rating' => 'T13']);
        $showtime = $this->existing($movie, $this->rooms['P01']);
        $showtime->load('ticketPrices.priceBookVersion', 'roomLayout');
        $source = $showtime->ticketPrices->firstOrFail()->priceBookVersion;
        $frozenAmount = (int) $showtime->ticketPrices->first()->final_unit_amount_vnd;

        $custom = SeatType::query()->create([
            'code' => 'daybed', 'name' => 'Ghế nằm', 'slug' => 'daybed',
            'is_pair' => false, 'status' => true, 'sort_order' => 90,
        ]);
        $couple = SeatType::query()->where('is_pair', true)->firstOrFail();
        $this->addSnapshot($showtime, $custom, $source, 135_000);
        if (! $showtime->ticketPrices()->where('seat_type_id', $couple->id)->exists()) {
            $this->addSnapshot($showtime, $couple, $source, 180_000);
        }

        $pinned = $showtime->roomLayout;
        $pinned->update(['status' => RoomLayout::STATUS_RETIRED]);
        RoomLayout::query()->create([
            'room_id' => $showtime->room_id,
            'version' => $pinned->version + 1,
            'name' => 'Latest layout must not replace pinned history',
            'rows' => 2,
            'columns' => 2,
            'screen_position' => 'top',
            'status' => RoomLayout::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $versions = app(PriceBookVersionService::class);
        $versions->retire($source);
        $later = $versions->createDraft($this->chainPriceBook(), [
            'base_price_vnd' => 199_000,
            'effective_from' => now()->subDay()->toDateString(),
            'effective_until' => now()->addYears(10)->toDateString(),
        ]);
        $versions->publish($later);

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.showtimes.show', $showtime))->assertOk()
            ->assertSee('Frozen Operational Movie')
            ->assertSee('105 phút')
            ->assertSee('Loại phòng')
            ->assertSee('Định dạng trình chiếu')
            ->assertSee($this->presentationFormat->name)
            ->assertSee('Phiên bản '.$pinned->version)
            ->assertSee('Giá đã khóa cho suất chiếu')
            ->assertSee('Nguồn bảng giá: v'.$source->version_number)
            ->assertSee('Ghế nằm')
            ->assertSee('135.000 VNĐ')
            ->assertSee('một cặp ghế vật lý')
            ->assertSee(number_format($frozenAmount, 0, ',', '.').' VNĐ')
            ->assertDontSee('199.000 VNĐ');

        $this->assertSame($pinned->id, $response->viewData('showtime')->room_layout_id);
        $this->assertSame($source->id, $response->viewData('showtime')->ticketPrices->first()->price_book_version_id);
    }

    public function test_cross_midnight_and_completed_during_cleaning_use_central_lifecycle_snapshot(): void
    {
        $showtime = $this->existing($this->movie(120, ['title' => 'Cross Midnight Detail']), $this->rooms['P01'], [
            'show_date' => '2026-08-11',
            'show_time' => '23:30:00',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 00:30:00', 'Asia/Ho_Chi_Minh'));
        try {
            $this->actingAs($this->userWithRole('admin'))
                ->get(route('admin.showtimes.show', $showtime))->assertOk()
                ->assertSee('Ngày nghiệp vụ')
                ->assertSee('11/08/2026')
                ->assertSee('11/08/2026 23:30')
                ->assertSee('12/08/2026 01:30')
                ->assertSee('12/08/2026 01:45')
                ->assertSee('Đang chiếu');

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 01:35:00', 'Asia/Ho_Chi_Minh'));
            $this->get(route('admin.showtimes.show', $showtime))->assertOk()
                ->assertSee('Đã chiếu xong')
                ->assertSee('Đang vệ sinh')
                ->assertDontSee('data-room-state="playing"', false);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_cancelled_showtime_is_historical_and_never_presented_as_playing(): void
    {
        $showtime = $this->existing($this->movie(90, ['title' => 'Cancelled Detail']), $this->rooms['P01'], [
            'show_date' => '2026-08-10',
            'show_time' => '10:00:00',
            'status' => 'cancelled',
        ]);
        $showtime->room()->update(['status' => 'inactive']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-11 12:00:00', 'Asia/Ho_Chi_Minh'));
        try {
            $this->actingAs($this->userWithRole('admin'))
                ->get(route('admin.showtimes.show', $showtime))->assertOk()
                ->assertSee('Trạng thái lưu trữ')
                ->assertSee('Đã hủy')
                ->assertSee('Không áp dụng — suất đã hủy')
                ->assertDontSee('data-showtime-state="playing"', false);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_booking_context_is_bounded_related_and_uses_booking_snapshot_amounts(): void
    {
        $showtime = $this->existing($this->movie(90, ['title' => 'Booking Context Movie']), $this->rooms['P01']);
        $codes = [];
        foreach (range(1, 12) as $number) {
            $codes[] = sprintf('SHOW-BOOK-%02d', $number);
            $this->booking($showtime, end($codes), [
                'booking_status' => $number % 3 === 0 ? 'cancelled' : ($number % 2 === 0 ? 'paid' : 'pending_payment'),
                'payment_status' => $number % 2 === 0 ? 'paid' : 'unpaid',
                'total_amount' => 120_000 + $number,
            ]);
        }
        $foreign = $this->foreignShowtime();
        $foreignBooking = $this->booking($foreign, 'FOREIGN-SHOWTIME-BOOKING', ['total_amount' => 999_999]);

        $response = $this->actingAs($this->userWithRole('manager'))
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('admin.showtimes.show', $showtime))->assertOk()
            ->assertSee('Tổng đơn đặt vé')
            ->assertSee('12 đơn')
            ->assertSee('SHOW-BOOK-12')
            ->assertSee('SHOW-BOOK-03')
            ->assertDontSee('SHOW-BOOK-02')
            ->assertDontSee($foreignBooking->booking_code)
            ->assertSee(route('admin.bookings.show', Booking::query()->where('booking_code', 'SHOW-BOOK-12')->firstOrFail()), false)
            ->assertSee('120.012 VNĐ');

        $this->assertCount(10, $response->viewData('recentBookings'));
        $this->assertSame(12, $response->viewData('bookingCount'));
    }

    public function test_showtime_list_and_branch360_use_actual_format_and_canonical_detail_link(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 17:00:00', 'Asia/Ho_Chi_Minh'));
        try {
            $showtime = $this->existing($this->movie(90, ['title' => 'Workspace Link Movie']), $this->rooms['P01'], [
                'show_date' => '2030-06-10',
                'show_time' => '18:00:00',
            ]);
            $detailUrl = route('admin.showtimes.show', $showtime);
            $manager = $this->userWithRole('manager');
            $session = [CinemaAccessService::SESSION_KEY => $this->cinema->id];

            $this->actingAs($manager)->withSession($session)
                ->get(route('admin.showtimes.index'))->assertOk()
                ->assertSee('Định dạng')
                ->assertSee($this->presentationFormat->name)
                ->assertSee('Xem chi tiết')
                ->assertSee($detailUrl, false);
            $detailHtml = $this->withSession($session)->get($detailUrl)->assertOk()->getContent();
            $navigation = $this->navigation($detailHtml);
            $this->assertSame(1, substr_count($navigation, 'aria-current="page"'));
            $this->assertMatchesRegularExpression(
                '/<a\b(?=[^>]*data-admin-nav-route="admin\.showtimes\.index")(?=[^>]*is-active)[^>]*>/u',
                $navigation,
            );
            $this->withSession($session)->get(route('admin.cinemas.show', $this->cinema))->assertOk()
                ->assertSee('Workspace Link Movie')
                ->assertSee($detailUrl, false);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_detail_contains_no_manual_price_attendance_or_occupancy_controls(): void
    {
        $showtime = $this->existing($this->movie(), $this->rooms['P01']);
        $html = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.showtimes.show', $showtime))->assertOk()->getContent();
        $priceSection = $this->section($html, 'data-showtime-price-snapshots');

        $this->assertStringNotContainsString('<input', $priceSection);
        $this->assertStringNotContainsString('<button', $priceSection);
        foreach (['data-manual-price-action', 'data-attendance-action', 'data-check-in-action', 'Tỷ lệ lấp đầy', 'Doanh thu suất chiếu'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }

    public function test_list_and_detail_query_counts_are_bounded_with_many_rows_and_dynamic_types(): void
    {
        $showtime = $this->existing($this->movie(), $this->rooms['P01']);
        $source = $showtime->ticketPrices()->firstOrFail()->priceBookVersion()->firstOrFail();
        foreach (range(1, 7) as $number) {
            $type = SeatType::query()->create([
                'code' => 'dynamic-'.$number,
                'name' => 'Dynamic '.$number,
                'slug' => 'dynamic-'.$number,
                'is_pair' => false,
                'status' => true,
                'sort_order' => 100 + $number,
            ]);
            $this->addSnapshot($showtime, $type, $source, 100_000 + $number);
        }
        foreach (range(1, 10) as $number) {
            $this->booking($showtime, 'QUERY-BOOK-'.$number);
        }

        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $managerSession = [CinemaAccessService::SESSION_KEY => $this->cinema->id];
        $counts = [
            'admin_list' => $this->countQueries(fn () => $this->actingAs($admin)
                ->withSession([CinemaAccessService::SESSION_KEY => 'all'])
                ->get(route('admin.showtimes.index'))->assertOk()),
            'manager_list' => $this->countQueries(fn () => $this->actingAs($manager)
                ->withSession($managerSession)->get(route('admin.showtimes.index'))->assertOk()),
            'admin_detail' => $this->countQueries(fn () => $this->actingAs($admin)
                ->withSession([CinemaAccessService::SESSION_KEY => 'all'])
                ->get(route('admin.showtimes.show', $showtime))->assertOk()),
            'manager_detail' => $this->countQueries(fn () => $this->actingAs($manager)
                ->withSession($managerSession)->get(route('admin.showtimes.show', $showtime))->assertOk()),
        ];

        foreach ($counts as $surface => $count) {
            $this->assertLessThanOrEqual(30, $count, $surface.' query budget exceeded: '.json_encode($counts));
        }
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'SHOWTIME_OPERATIONAL_WORKSPACE_QUERY_COUNTS='.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    private function foreignShowtime(): Showtime
    {
        $cinema = Cinema::factory()->create([
            'status' => 'active', 'archived_at' => null, 'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        $room = Room::factory()->create(['cinema_id' => $cinema->id]);
        $layout = $this->publishedRoomLayoutFixture($room);
        $movie = Movie::query()->create([
            'title' => 'Foreign Showtime Movie '.str()->random(6),
            'slug' => 'foreign-showtime-'.str()->lower(str()->random(8)),
            'duration' => 90,
            'age_rating' => 'P',
            'status' => 'now_showing',
        ]);

        return Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id,
            'show_date' => '2030-06-10',
            'show_time' => '20:00:00',
            'status' => 'active',
        ]);
    }

    private function booking(Showtime $showtime, string $code, array $overrides = []): Booking
    {
        return Booking::query()->create([
            'showtime_id' => $showtime->id,
            'booking_code' => $code,
            'customer_name' => 'Khách '.$code,
            'customer_email' => str()->lower($code).'@example.test',
            'seat_subtotal' => 120_000,
            'food_subtotal' => 0,
            'gross_amount' => 120_000,
            'promotion_discount_amount' => 0,
            'total_amount' => 120_000,
            'currency' => 'VND',
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
            ...$overrides,
        ]);
    }

    private function addSnapshot(Showtime $showtime, SeatType $type, PriceBookVersion $source, int $amount): ShowtimeTicketPrice
    {
        return ShowtimeTicketPrice::query()->create([
            'showtime_id' => $showtime->id,
            'seat_type_id' => $type->id,
            'price_book_version_id' => $source->id,
            'base_price_vnd' => $amount,
            'adjustment_total_vnd' => 0,
            'final_unit_amount_vnd' => $amount,
            'breakdown_json' => [
                'version_number' => $source->version_number,
                'adjustments' => [],
            ],
            'pricing_fingerprint' => hash('sha256', $showtime->id.'|'.$type->id.'|'.$amount),
        ]);
    }

    private function section(string $html, string $attribute): string
    {
        $start = strpos($html, '<section '.$attribute);
        $this->assertIsInt($start);
        $end = strpos($html, '</section>', $start);
        $this->assertIsInt($end);

        return substr($html, $start, $end - $start);
    }

    private function navigation(string $html): string
    {
        $start = strpos($html, '<nav class="admin-sidebar-scroll');
        $this->assertIsInt($start);
        $end = strpos($html, '</nav>', $start);
        $this->assertIsInt($end);

        return substr($html, $start, $end - $start);
    }

    private function countQueries(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $operation();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
