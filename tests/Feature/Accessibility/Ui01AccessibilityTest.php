<?php

namespace Tests\Feature\Accessibility;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class Ui01AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_admin_layout_removes_dead_controls_and_exposes_operable_navigation_landmarks(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="#admin-main-content"', false)
            ->assertSee('id="admin-main-content"', false)
            ->assertSee('data-admin-mobile-drawer', false)
            ->assertSee('aria-label="Điều hướng quản trị"', false)
            ->assertDontSee('Tìm kiếm quản trị')
            ->assertDontSee('aria-label="Thông báo"', false);

        $html = $response->getContent();
        $this->assertStringContainsString('data-admin-nav-route="admin.dashboard"', $html);
        $this->assertStringContainsString("event.key === 'Escape'", $html);
        $this->assertStringContainsString("event.key !== 'Tab'", $html);
        $this->assertStringContainsString('adminMain.inert = true', $html);
        $this->assertStringContainsString('toggleBtn.focus()', $html);
    }

    public function test_skip_links_have_unique_valid_targets_for_each_primary_layout(): void
    {
        $admin = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.dashboard'))->assertOk()->getContent();
        $staff = $this->actingAs($this->userWithRole('staff'))
            ->get(route('staff.dashboard'))->assertOk()->getContent();
        $customer = $this->get(route('user.movies.index'))->assertOk()->getContent();

        foreach ([[$admin, 'admin-main-content'], [$staff, 'staff-main-content'], [$customer, 'main-content']] as [$html, $target]) {
            $this->assertSame(1, substr_count($html, 'href="#'.$target.'"'));
            $this->assertSame(1, substr_count($html, 'id="'.$target.'"'));
            $this->assertStringContainsString('Bỏ qua điều hướng, đến nội dung chính', $html);
        }
    }

    public function test_customer_filters_and_chatbot_have_accessible_names_and_semantics(): void
    {
        $movies = $this->get(route('user.movies.index'))->assertOk();
        foreach ([
            'Tìm kiếm phim', 'Lọc theo thời lượng', 'Lọc theo thể loại', 'Chi nhánh',
            'Ngày chiếu', 'Lọc theo quốc gia', 'Lọc theo trạng thái phim',
            'Lọc theo độ tuổi', 'Sắp xếp phim',
        ] as $label) {
            $movies->assertSee('<span class="sr-only">'.$label.'</span>', false);
        }

        $customer = $this->userWithRole('customer');
        $this->actingAs($customer)->get(route('user.bookings.history'))
            ->assertOk()
            ->assertSee('<span class="sr-only">Lọc đơn theo trạng thái</span>', false);

        $this->get(route('user.ai.chatbot'))
            ->assertOk()
            ->assertSee('for="chat-input"', false)
            ->assertSee('aria-label="Gửi câu hỏi cho MovieMate AI"', false)
            ->assertSee('role="log"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('aria-label="Cuộc trò chuyện với MovieMate AI"', false);
    }

    public function test_showtime_edit_action_has_an_accessible_name_and_visible_focus_contract(): void
    {
        $template = File::get(resource_path('views/admin/showtimes/index.blade.php'));

        $this->assertStringContainsString('data-showtime-edit-action', $template);
        $this->assertStringContainsString('aria-label="Chỉnh sửa suất chiếu', $template);
        $this->assertStringContainsString('focus-visible:ring-2 focus-visible:ring-brand-start', $template);
        $this->assertStringContainsString('aria-hidden="true"', $template);
    }
}
