<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
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
    }

    public function test_dashboard_access_follows_guest_customer_staff_manager_admin_and_inactive_rules(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('user'))->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->userWithRole('admin'))->get(route('admin.dashboard'))->assertOk();

        $inactive = $this->userWithRole('admin', ['status' => 'inactive']);
        $this->actingAs($inactive)->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_empty_dashboard_renders_a_focused_operational_workspace_in_vietnamese(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tổng quan hệ thống')
            ->assertSee('Trung tâm điều hành hôm nay')
            ->assertSee('Doanh thu hôm nay')
            ->assertSee('Đơn đã thanh toán')
            ->assertSee('Vé / chỗ đã bán')
            ->assertSee('Suất chiếu hôm nay')
            ->assertSee('Vận hành phòng hôm nay')
            ->assertSee('Việc cần xử lý')
            ->assertSee('Lối tắt vận hành')
            ->assertDontSee('Bộ lọc báo cáo')
            ->assertDontSee('Top phim')
            ->assertDontSee('Khung giờ cao điểm')
            ->assertDontSee('Thể loại được quan tâm')
            ->assertDontSee('Phương thức thanh toán')
            ->assertDontSee('customer_email')
            ->assertDontSee('transaction_code')
            ->assertDontSee('ticket_email_token');

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_dashboard_shows_only_current_cleaning_and_upcoming_room_work_for_the_selected_context(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-17 15:00:00', 'Asia/Ho_Chi_Minh'));
        config(['app.timezone' => 'UTC']);
        $primary = Cinema::query()->active()->firstOrFail();
        $primary->update(['name' => 'Chi nhánh vận hành chính']);
        $other = Cinema::factory()->create([
            'name' => 'Chi nhánh ngoài phạm vi',
            'status' => 'active',
            'archived_at' => null,
        ]);
        $movie = Movie::query()->create([
            'title' => 'Phim kiểm thử vận hành',
            'slug' => 'phim-kiem-thu-van-hanh',
            'duration' => 120,
            'age_rating' => 'P',
            'status' => Movie::STATUS_NOW_SHOWING,
        ]);

        $this->showtime($primary, $movie, 'P01', 'Đang chiếu chính', '14:00:00', 120, 15);
        $this->showtime($primary, $movie, 'P02', 'Đang vệ sinh chính', '13:00:00', 90, 60);
        $this->showtime($primary, $movie, 'P03', 'Sắp chiếu chính', '16:00:00', 120, 15);
        $this->showtime($primary, $movie, 'P04', 'Đã hoàn tất chính', '09:00:00', 90, 15);
        $this->showtime($other, $movie, 'O01', 'Suất ngoài phạm vi', '16:30:00', 120, 15);

        $admin = $this->userWithRole('admin');
        $response = $this->actingAs($admin)
            ->withSession([CinemaAccessService::SESSION_KEY => $primary->id])
            ->get(route('admin.dashboard', ['from' => '2025-01-01', 'cinema' => $other->id]))
            ->assertOk()
            ->assertSee('Chi nhánh vận hành chính')
            ->assertSee('trong ngày 17/08/2026')
            ->assertSee('Đang chiếu chính')
            ->assertSee('Đang vệ sinh chính')
            ->assertSee('Sắp chiếu chính')
            ->assertDontSee('Đã hoàn tất chính')
            ->assertDontSee('Suất ngoài phạm vi')
            ->assertDontSee('Bộ lọc báo cáo');

        $this->assertSame(1, substr_count($response->getContent(), '>Đang chiếu</dt>'));
        $this->assertSame(1, substr_count($response->getContent(), '>Đang vệ sinh</dt>'));
        $this->assertSame(1, substr_count($response->getContent(), '>Sắp chiếu</dt>'));
    }

    private function showtime(
        Cinema $cinema,
        Movie $movie,
        string $roomCode,
        string $roomName,
        string $showTime,
        int $duration,
        int $cleaningMinutes,
    ): Showtime {
        $room = Room::factory()->create([
            'cinema_id' => $cinema->id,
            'code' => $roomCode,
            'name' => $roomName,
            'cleaning_buffer_minutes' => $cleaningMinutes,
        ]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Sơ đồ '.$roomCode,
            'rows' => 1,
            'columns' => 1,
            'screen_position' => 'top',
            'status' => RoomLayout::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
        $format = PresentationFormat::query()->firstOrCreate(
            ['code' => 'DASHBOARD_2D'],
            ['name' => 'Dashboard 2D', 'is_active' => true, 'sort_order' => 1],
        );
        $showtimeMovie = $movie->replicate(['slug']);
        $showtimeMovie->forceFill([
            'title' => $roomName,
            'slug' => str($roomName)->slug().'-'.strtolower($roomCode),
            'duration' => $duration,
        ])->save();
        $showtime = new Showtime;
        $showtime->forceFill([
            'movie_id' => $showtimeMovie->id,
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $format->id,
            'show_date' => '2026-08-17',
            'show_time' => $showTime,
            'status' => 'active',
        ])->save();

        return $showtime;
    }
}
