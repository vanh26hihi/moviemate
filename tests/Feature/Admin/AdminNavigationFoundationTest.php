<?php

namespace Tests\Feature\Admin;

use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_sidebar_has_one_named_route_active_and_no_standalone_seat_item(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))->get(route('admin.movies.index'));

        $response->assertOk();
        $navigation = $this->navigationHtml($response->getContent());

        $this->assertSame(1, substr_count($navigation, 'aria-current="page"'));
        $this->assertStringContainsString('is-active', $this->anchorFor($navigation, 'admin.movies.index'));
        $this->assertStringNotContainsString('is-active', $this->anchorFor($navigation, 'admin.genres.index'));
        $this->assertDoesNotMatchRegularExpression('/>\s*Ghế\s*</u', $navigation);
    }

    public function test_only_menu_area_scrolls_between_fixed_logo_and_footer(): void
    {
        $html = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $logo = strpos($html, 'data-admin-sidebar-logo');
        $scroll = strpos($html, 'data-admin-sidebar-scroll');
        $navEnd = strpos($html, '</nav>', $scroll);
        $footer = strpos($html, 'data-admin-sidebar-footer');

        $this->assertIsInt($logo);
        $this->assertIsInt($scroll);
        $this->assertIsInt($navEnd);
        $this->assertIsInt($footer);
        $this->assertTrue($logo < $scroll && $scroll < $navEnd && $navEnd < $footer);

        $sidebar = substr($html, strpos($html, '<aside id="sidebar"'), strpos($html, '</aside>') - strpos($html, '<aside id="sidebar"'));
        $this->assertSame(1, substr_count($sidebar, 'overflow-y-auto'));
    }

    public function test_seat_maintenance_keeps_room_navigation_item_as_the_only_active_item(): void
    {
        $room = Room::factory()->create(['cinema_id' => app(CinemaContext::class)->id()]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Sơ đồ hiện hành',
            'rows' => 1,
            'columns' => 1,
            'screen_position' => 'top',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $seat = Seat::query()->create([
            'room_id' => $room->id,
            'row' => 'A',
            'number' => 1,
            'seat_code' => 'A1',
            'type' => 'normal',
            'status' => 'active',
        ]);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id,
            'x_position' => 1,
            'y_position' => 1,
            'cell_type' => 'seat',
            'seat_id' => $seat->id,
        ]);

        $navigation = $this->navigationHtml(
            $this->actingAs($this->userWithRole('admin'))
                ->get(route('admin.rooms.seat-maintenance.index', $room))
                ->assertOk()
                ->getContent()
        );

        $this->assertSame(1, substr_count($navigation, 'aria-current="page"'));
        $this->assertStringContainsString('is-active', $this->anchorFor($navigation, 'admin.rooms.index'));
        $this->assertDoesNotMatchRegularExpression('/>\s*Ghế\s*</u', $navigation);
    }

    public function test_protected_and_unimplemented_links_are_not_rendered(): void
    {
        $managerHtml = $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.dashboard'))->assertOk()->getContent();
        $managerNavigation = $this->navigationHtml($managerHtml);

        $this->assertStringNotContainsString('admin.activity-logs.index', $managerNavigation);
        // Multi-cinema gives Manager scoped 'users.view' so it can manage branch Staff
        // assignments. The list itself stays scoped to assigned branches by UserController.
        $this->assertStringContainsString('admin.users.index', $managerNavigation);
        $this->assertStringNotContainsString('admin.roles.index', $managerNavigation);
        $this->assertStringContainsString('admin.bookings.index', $managerNavigation);
        $this->assertStringContainsString('admin.payments.index', $managerNavigation);
        $this->assertStringNotContainsString('admin.payment-reconciliation.index', $managerNavigation);
        $this->assertStringNotContainsString('Đối soát giao dịch', $managerNavigation);
        $this->assertStringNotContainsString(route('admin.payment-reconciliation.index'), $managerNavigation);
        $this->assertSame(1, substr_count($managerHtml, 'data-admin-sidebar-scroll'));
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringNotContainsString('paymentReconciliationBadge', $provider);
        $this->assertStringNotContainsString('badgeLabel()', $provider);
        $this->assertStringNotContainsString('admin.ticket-deliveries.index', $managerNavigation);
        $this->assertStringNotContainsString('Gửi vé điện tử', $managerNavigation);
        $this->assertStringContainsString('admin.ticket-checkins.index', $managerNavigation);
        $this->assertStringContainsString('admin.reports.index', $managerNavigation);
        foreach (['Tổng quan', 'Nội dung', 'Rạp &amp; lịch chiếu', 'Kinh doanh', 'Dịch vụ', 'Hệ thống'] as $group) {
            $this->assertStringContainsString($group, $managerNavigation);
        }
        foreach (['admin.discounts.index', 'admin.reviews.index'] as $missingRoute) {
            $this->assertStringNotContainsString($missingRoute, $managerNavigation);
        }

        $adminNavigation = $this->navigationHtml(
            $this->actingAs($this->userWithRole('admin'))->get(route('admin.dashboard'))->assertOk()->getContent()
        );
        $this->assertStringContainsString('admin.activity-logs.index', $adminNavigation);
        $this->assertStringNotContainsString('admin.payment-reconciliation.index', $adminNavigation);
        $this->assertStringNotContainsString('Đối soát giao dịch', $adminNavigation);
    }

    public function test_admin_success_notification_is_rendered_once(): void
    {
        $message = 'Thao tác quản trị đã hoàn tất.';
        $html = $this->actingAs($this->userWithRole('admin'))
            ->withSession(['success' => $message])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count(strip_tags($html), $message));
    }

    public function test_success_lists_keep_a_compact_allowlisted_filter_contract(): void
    {
        $admin = $this->userWithRole('admin');
        $bookings = $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk();

        foreach (['search', 'cinema_id', 'sales_channel', 'date_from', 'date_to', 'ticket_status', 'checkin_status', 'sort', 'direction', 'per_page'] as $name) {
            $bookings->assertSee('name="'.$name.'"', false);
        }
        foreach (['include_drafts', 'booking_status', 'payment_status', 'provider', 'amount_min', 'amount_max', 'movie_id', 'room_id', 'customer_email', 'created_by'] as $name) {
            $bookings->assertDontSee('name="'.$name.'"', false);
        }

        $payments = $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk();
        foreach (['search', 'cinema_id', 'provider', 'sales_channel', 'date_from', 'date_to', 'sort', 'direction', 'per_page'] as $name) {
            $payments->assertSee('name="'.$name.'"', false);
        }
        foreach (['status', 'review', 'mismatch', 'reconciled', 'verified', 'amount_min', 'amount_max', 'include_drafts', 'response_code', 'transaction_status'] as $name) {
            $payments->assertDontSee('name="'.$name.'"', false);
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
}
