<?php

namespace Tests\Feature\Admin;

use App\Models\Cinema;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\RoomType;
use App\Models\UserCinemaAssignment;
use App\Services\CinemaAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPriceBookFixtures;
use Tests\TestCase;

final class NavigationOwnershipTest extends TestCase
{
    use CreatesPriceBookFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_global_admin_navigation_expresses_chain_governance(): void
    {
        $navigation = $this->navigationHtml(
            $this->actingAs($this->userWithRole('admin'))
                ->get(route('admin.dashboard'))->assertOk()->getContent(),
        );

        foreach (['Tổng quan', 'Vận hành', 'Kinh doanh', 'Khách hàng', 'Tài chính', 'Cấu hình'] as $group) {
            $this->assertStringContainsString($group, $navigation);
        }
        foreach ([
            'Chi nhánh', 'Lịch vận hành', 'Phòng chiếu', 'Đơn đặt vé', 'Đơn đồ ăn tại rạp',
            'Phim', 'Bảng giá', 'Khuyến mãi', 'Món ăn', 'Đánh giá phim',
            'Thanh toán', 'Báo cáo', 'Loại phòng', 'Mẫu sơ đồ', 'Định dạng trình chiếu',
            'Người dùng', 'Vai trò và quyền', 'Nhật ký hoạt động',
        ] as $label) {
            $this->assertStringContainsString($label, $navigation);
        }
        foreach ([
            'admin.cinemas.index', 'admin.showtimes.index', 'admin.rooms.index', 'admin.bookings.index',
            'admin.food-orders.index', 'admin.movies.index', 'admin.price-books.index', 'admin.discounts.index',
            'admin.foods.index', 'admin.reviews.index', 'admin.payments.index', 'admin.reports.index',
            'admin.room-types.index', 'admin.layout-templates.index', 'admin.presentation-formats.index',
            'admin.users.index', 'admin.roles.index', 'admin.activity-logs.index',
        ] as $routeName) {
            $this->assertStringContainsString('data-admin-nav-route="'.$routeName.'"', $navigation);
        }

        $this->assertStringNotContainsString('data-admin-nav-route="admin.genres.index"', $navigation);
        $this->assertStringNotContainsString('PresentationFormat', $navigation);
    }

    public function test_manager_navigation_is_branch_first_and_distinguishes_read_only_chain_masters(): void
    {
        $manager = $this->userWithRole('manager');
        $navigation = $this->navigationHtml(
            $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk()->getContent(),
        );

        foreach (['Tổng quan chi nhánh', 'Vận hành', 'Kinh doanh', 'Báo cáo'] as $group) {
            $this->assertStringContainsString($group, $navigation);
        }
        foreach ([
            'Lịch vận hành', 'Phòng chiếu', 'Đơn đặt vé', 'Thanh toán chi nhánh',
            'Đơn đồ ăn tại rạp', 'Nhân sự chi nhánh', 'Danh mục phim · Chỉ xem',
            'Bảng giá áp dụng', 'Khuyến mãi', 'Món ăn dùng chung · Chỉ xem', 'Báo cáo chi nhánh',
        ] as $label) {
            $this->assertStringContainsString($label, $navigation);
        }
        foreach ([
            'admin.roles.index', 'admin.activity-logs.index', 'admin.room-types.index',
            'admin.layout-templates.index', 'admin.presentation-formats.index', 'admin.genres.index',
        ] as $routeName) {
            $this->assertStringNotContainsString('data-admin-nav-route="'.$routeName.'"', $navigation);
        }
        foreach (['Hệ thống', 'Nội dung', 'Rạp &amp; lịch chiếu', 'Tổng quan hệ thống'] as $oldMentalModel) {
            $this->assertStringNotContainsString($oldMentalModel, $navigation);
        }

        $this->get(route('admin.movies.index'))->assertOk()
            ->assertSee('Danh mục phim dùng chung')->assertDontSee(route('admin.movies.create'), false);
        $this->get(route('admin.foods.index'))->assertOk()
            ->assertSee('Danh mục món ăn dùng chung')->assertDontSee(route('admin.foods.create'), false);
        $this->chainPriceBook();
        $this->get(route('admin.price-books.index'))->assertOk()
            ->assertSee('Xem bảng giá áp dụng tại chi nhánh')->assertSee('Chỉ xem · chi nhánh hiện tại');
        $this->get(route('admin.discounts.index'))->assertOk()
            ->assertSee('Khuyến mãi')->assertSee(route('admin.discounts.create'), false)
            ->assertSee('Thêm khuyến mãi');
    }

    public function test_dashboard_identity_and_context_are_truthful_for_global_admin_and_manager(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->withSession([CinemaAccessService::SESSION_KEY => 'all'])
            ->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Tổng quan hệ thống')
            ->assertSee('Phạm vi quản trị')
            ->assertSee('Toàn hệ thống')
            ->assertDontSee('Tổng quan chi nhánh');

        $manager = $this->userWithRole('manager');
        $cinema = $manager->activeCinemaAssignments()->with('cinema')->firstOrFail()->cinema;
        $this->actingAs($manager)->withSession([CinemaAccessService::SESSION_KEY => $cinema->id])
            ->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Tổng quan chi nhánh')
            ->assertSee('Tổng quan — '.$cinema->name)
            ->assertSee('Chi nhánh hiện tại')
            ->assertSee($cinema->name)
            ->assertSee(route('admin.cinemas.show', $cinema), false)
            ->assertDontSee('Tổng quan hệ thống');
    }

    public function test_multi_branch_manager_identity_uses_selected_authorized_cinema_and_long_name_wraps(): void
    {
        $manager = $this->userWithRole('manager');
        $longName = 'MovieMate Trung tâm Điện ảnh và Văn hóa Thành phố Hồ Chí Minh — Chi nhánh Nguyễn Huệ';
        $second = Cinema::factory()->create([
            'name' => $longName,
            'status' => 'active',
            'archived_at' => null,
        ]);
        UserCinemaAssignment::query()->create([
            'user_id' => $manager->id,
            'cinema_id' => $second->id,
            'status' => UserCinemaAssignment::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $second->id])
            ->get(route('admin.dashboard'))->assertOk()
            ->assertSee($longName)
            ->assertSee('data-admin-context-name', false)
            ->assertSee('break-words', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-admin-context-banner'));
    }

    public function test_active_state_and_canonical_navigation_routes_remain_stable(): void
    {
        RoomType::query()->firstOrCreate(['code' => 'NAV_TEST'], ['name' => 'Navigation Test', 'is_active' => true]);
        $admin = $this->userWithRole('admin');
        $navigation = $this->navigationHtml(
            $this->actingAs($admin)->get(route('admin.room-types.index'))->assertOk()->getContent(),
        );

        $this->assertSame(1, substr_count($navigation, 'aria-current="page"'));
        $this->assertStringContainsString('is-active', $this->anchorFor($navigation, 'admin.room-types.index'));
        foreach (['admin.cinema.show', 'admin.seats.index'] as $compatibilityRoute) {
            $this->assertStringNotContainsString('data-admin-nav-route="'.$compatibilityRoute.'"', $navigation);
        }
        foreach (['/my-ticket/', '/foods/cart', '/foods/checkout', '/admin/seats'] as $compatibilityPath) {
            $this->assertStringNotContainsString($compatibilityPath, $navigation);
        }
    }

    public function test_canonical_module_and_catalog_subroutes_have_exactly_one_primary_active_item(): void
    {
        $movie = Movie::query()->create([
            'title' => 'Navigation Active Movie',
            'slug' => 'navigation-active-movie',
            'duration' => 100,
            'age_rating' => 'P',
            'status' => Movie::STATUS_DRAFT,
        ]);
        $genre = Genre::query()->create([
            'name' => 'Navigation Active Genre',
            'slug' => 'navigation-active-genre',
        ]);
        $roomType = RoomType::query()->create([
            'code' => 'NAV_ACTIVE',
            'name' => 'Navigation Active Room Type',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $presentationFormat = PresentationFormat::query()->create([
            'code' => 'NAV_ACTIVE',
            'name' => 'Navigation Active Format',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $this->chainPriceBook();
        $this->actingAs($this->userWithRole('admin'));

        $cases = [
            [route('admin.movies.index'), 'admin.movies.index'],
            [route('admin.movies.create'), 'admin.movies.index'],
            [route('admin.movies.edit', $movie), 'admin.movies.index'],
            [route('admin.movies.show', $movie), 'admin.movies.index'],
            [route('admin.genres.index'), 'admin.movies.index'],
            [route('admin.genres.create'), 'admin.movies.index'],
            [route('admin.genres.edit', $genre), 'admin.movies.index'],
            [route('admin.room-types.index'), 'admin.room-types.index'],
            [route('admin.room-types.create'), 'admin.room-types.index'],
            [route('admin.room-types.edit', $roomType), 'admin.room-types.index'],
            [route('admin.presentation-formats.index'), 'admin.presentation-formats.index'],
            [route('admin.presentation-formats.create'), 'admin.presentation-formats.index'],
            [route('admin.presentation-formats.edit', $presentationFormat), 'admin.presentation-formats.index'],
            [route('admin.price-books.index'), 'admin.price-books.index'],
            [route('admin.discounts.index'), 'admin.discounts.index'],
            [route('admin.bookings.index'), 'admin.bookings.index'],
            [route('admin.payments.index'), 'admin.payments.index'],
            [route('admin.reports.index'), 'admin.reports.index'],
            [route('admin.showtimes.index'), 'admin.showtimes.index'],
        ];

        foreach ($cases as [$url, $expectedRoute]) {
            $navigation = $this->navigationHtml($this->get($url)->assertOk()->getContent());
            $this->assertSame(1, substr_count($navigation, 'aria-current="page"'), $url);
            $this->assertStringContainsString('is-active', $this->anchorFor($navigation, $expectedRoute), $url);
        }
    }

    public function test_genre_remains_reachable_as_global_admin_movie_catalog_support(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('admin.movies.index'))->assertOk()
            ->assertSee('Thể loại phim')
            ->assertSee(route('admin.genres.index'), false);

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get(route('admin.movies.index'))->assertOk()
            ->assertDontSee(route('admin.genres.index'), false);
    }

    public function test_navigation_does_not_add_per_link_queries(): void
    {
        $counts = [
            'global_admin' => $this->countQueries(fn () => $this->actingAs($this->userWithRole('admin'))
                ->get(route('admin.dashboard'))->assertOk()),
            'manager' => $this->countQueries(fn () => $this->actingAs($this->userWithRole('manager'))
                ->get(route('admin.dashboard'))->assertOk()),
        ];

        foreach ($counts as $role => $count) {
            $this->assertLessThanOrEqual(30, $count, "{$role} dashboard query budget exceeded: ".json_encode($counts));
        }
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'NAVIGATION_OWNERSHIP_QUERY_COUNTS='.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    private function navigationHtml(string $html): string
    {
        $start = strpos($html, '<nav class="admin-sidebar-scroll');
        $this->assertIsInt($start);
        $end = strpos($html, '</nav>', $start);
        $this->assertIsInt($end);

        return substr($html, $start, $end - $start);
    }

    private function anchorFor(string $navigation, string $routeName): string
    {
        $matched = preg_match(
            '/<a\b[^>]*data-admin-nav-route="'.preg_quote($routeName, '/').'"[^>]*>.*?<\/a>/su',
            $navigation,
            $matches,
        );
        $this->assertSame(1, $matched, "Không tìm thấy mục điều hướng {$routeName}");

        return $matches[0];
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
