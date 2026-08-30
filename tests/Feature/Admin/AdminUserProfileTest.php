<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Review;
use Database\Seeders\CinemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class AdminUserProfileTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        $this->seed(CinemaSeeder::class);
    }

    public function test_profile_routes_require_authentication_and_user_view_permission(): void
    {
        $target = $this->userWithRole('user');

        $this->get(route('admin.users.show', $target))->assertRedirect(route('login'));
        $this->get(route('admin.users.bookings.export', $target))->assertRedirect(route('login'));

        $customer = $this->userWithRole('user');
        $this->actingAs($customer)->get(route('admin.users.show', $target))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.users.bookings.export', $target))->assertForbidden();
    }

    public function test_admin_sees_account_summary_assignments_and_empty_states(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('user', [
            'name' => 'Nguyễn Khách Hàng',
            'email' => 'customer.profile@example.test',
        ]);

        $this->actingAs($admin)->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee('Nguyễn Khách Hàng')
            ->assertSee('customer.profile@example.test')
            ->assertSee('Lịch sử đặt vé')
            ->assertSee('Không có đơn phù hợp')
            ->assertSee('Không có lịch sử phân công')
            ->assertSee(route('admin.users.bookings.export', $target), false);
    }

    public function test_profile_summarizes_and_filters_target_bookings_without_showing_other_users(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('user');
        $other = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);

        $paid = $this->bookingForScenario($scenario, [
            'user_id' => $target->id,
            'booking_code' => 'PROFILE-PAID-001',
            'booking_status' => 'paid',
            'payment_status' => 'paid',
            'total_amount' => 125000,
        ]);
        $expired = $this->bookingForScenario($scenario, [
            'user_id' => $target->id,
            'booking_code' => 'PROFILE-EXPIRED-002',
            'booking_status' => 'expired',
        ]);
        $foreign = $this->bookingForScenario($scenario, [
            'user_id' => $other->id,
            'booking_code' => 'PROFILE-FOREIGN-003',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', [
            'user' => $target,
            'booking_status' => 'paid',
        ]));

        $response->assertOk()
            ->assertSee($paid->booking_code)
            ->assertDontSee($expired->booking_code)
            ->assertDontSee($foreign->booking_code)
            ->assertSee('125.000 VNĐ')
            ->assertSee('1 đơn đã thanh toán');
    }

    public function test_profile_searches_by_booking_code_and_movie_title(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $scenario['movie']->update(['title' => 'Hành Trình Ánh Sáng']);

        $booking = $this->bookingForScenario($scenario, [
            'user_id' => $target->id,
            'booking_code' => 'LIGHT-SEARCH-001',
        ]);

        $this->actingAs($admin)->get(route('admin.users.show', [
            'user' => $target,
            'booking_search' => 'Ánh Sáng',
        ]))->assertOk()->assertSee($booking->booking_code);

        $this->actingAs($admin)->get(route('admin.users.show', [
            'user' => $target,
            'booking_search' => 'LIGHT-SEARCH',
        ]))->assertOk()->assertSee('Hành Trình Ánh Sáng');
    }

    public function test_profile_includes_review_and_related_activity_aggregates(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);

        Review::query()->create([
            'user_id' => $target->id,
            'movie_id' => $scenario['movie']->id,
            'rating' => 4,
            'comment' => 'Phim hay',
        ]);
        ActivityLog::query()->create([
            'actor_user_id' => $admin->id,
            'actor_role_snapshot' => 'admin',
            'action' => 'user.status_updated',
            'subject_type' => $target::class,
            'subject_id' => (string) $target->id,
            'subject_label' => $target->email,
        ]);

        $this->actingAs($admin)->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee('4 / 5 điểm trung bình')
            ->assertSee('user status updated')
            ->assertSee($admin->name);
    }

    public function test_booking_filters_reject_invalid_status_and_inverted_date_range(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('user');
        $from = route('admin.users.show', $target);

        $this->actingAs($admin)->from($from)->get(route('admin.users.show', [
            'user' => $target,
            'booking_status' => 'refunded',
        ]))->assertRedirect($from)->assertSessionHasErrors('booking_status');

        $this->actingAs($admin)->from($from)->get(route('admin.users.show', [
            'user' => $target,
            'date_from' => '2026-08-20',
            'date_to' => '2026-08-19',
        ]))->assertRedirect($from)->assertSessionHasErrors('date_to');
    }

    public function test_csv_export_contains_filtered_target_rows_and_safe_headers(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $included = $this->bookingForScenario($scenario, [
            'user_id' => $target->id,
            'booking_code' => 'CSV-PAID-001',
            'booking_status' => 'paid',
            'payment_status' => 'paid',
        ]);
        $excluded = $this->bookingForScenario($scenario, [
            'user_id' => $target->id,
            'booking_code' => 'CSV-EXPIRED-002',
            'booking_status' => 'expired',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.bookings.export', [
            'user' => $target,
            'booking_status' => 'paid',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff');

        $csv = $response->streamedContent();
        $this->assertStringContainsString($included->booking_code, $csv);
        $this->assertStringNotContainsString($excluded->booking_code, $csv);
        $this->assertStringContainsString('Mã đơn', $csv);
    }

    public function test_user_index_links_to_the_operational_profile(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('user', ['name' => 'Profile Link Target']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Profile Link Target')
            ->assertSee(route('admin.users.show', $target), false);
    }
}
