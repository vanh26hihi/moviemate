<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\DashboardController;
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

    public function test_empty_dashboard_renders_all_safe_reporting_sections_in_vietnamese(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tổng quan hệ thống')
            ->assertSee('Bộ lọc báo cáo')
            ->assertSee('Doanh thu')
            ->assertSee('Đơn vị vé bán')
            ->assertSee('Số chỗ')
            ->assertSee('Đơn đã thanh toán')
            ->assertSee('Lịch chiếu hôm nay')
            ->assertSee('Top phim')
            ->assertSee('Khung giờ cao điểm')
            ->assertSee('Thể loại được quan tâm')
            ->assertSee('Kênh bán')
            ->assertSee('Phương thức thanh toán')
            ->assertSee('Vận hành in vé', false)
            ->assertSee('Chưa có dữ liệu trong khoảng thời gian đã chọn.')
            ->assertDontSee('customer_email')
            ->assertDontSee('transaction_code')
            ->assertDontSee('ticket_email_token');

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }
}
