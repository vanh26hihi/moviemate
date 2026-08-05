<?php

namespace Tests\Feature\Admin;

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
        $navigation = $this->navigationHtml(
            $this->actingAs($this->userWithRole('admin'))->get(route('admin.seats.index'))->assertOk()->getContent()
        );

        $this->assertSame(1, substr_count($navigation, 'aria-current="page"'));
        $this->assertStringContainsString('is-active', $this->anchorFor($navigation, 'admin.rooms.index'));
        $this->assertDoesNotMatchRegularExpression('/>\s*Ghế\s*</u', $navigation);
    }

    public function test_protected_and_unimplemented_links_are_not_rendered(): void
    {
        $managerNavigation = $this->navigationHtml(
            $this->actingAs($this->userWithRole('manager'))->get(route('admin.dashboard'))->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('admin.activity-logs.index', $managerNavigation);
        $this->assertStringNotContainsString('admin.users.index', $managerNavigation);
        $this->assertStringNotContainsString('admin.roles.index', $managerNavigation);
        foreach (['admin.bookings.index', 'admin.payments.index', 'admin.discounts.index', 'admin.reports.index', 'admin.ticket-deliveries.index', 'admin.ticket-checkins.index', 'admin.reviews.index'] as $missingRoute) {
            $this->assertStringNotContainsString($missingRoute, $managerNavigation);
        }

        $adminNavigation = $this->navigationHtml(
            $this->actingAs($this->userWithRole('admin'))->get(route('admin.dashboard'))->assertOk()->getContent()
        );
        $this->assertStringContainsString('admin.activity-logs.index', $adminNavigation);
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
