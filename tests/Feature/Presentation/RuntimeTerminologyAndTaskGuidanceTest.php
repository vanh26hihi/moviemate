<?php

namespace Tests\Feature\Presentation;

use App\Models\Room;
use App\Models\RoomType;
use App\Services\CinemaAccessService;
use Tests\Feature\Payments\PaymentTestCase;

final class RuntimeTerminologyAndTaskGuidanceTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_customer_empty_booking_history_has_a_canonical_discovery_action(): void
    {
        $customer = $this->userWithRole('user');

        $this->actingAs($customer)->get(route('user.bookings.history'))
            ->assertOk()
            ->assertSee('Bạn chưa có đơn đặt vé nào')
            ->assertSee('Tìm suất chiếu')
            ->assertSee(route('user.movies.index'), false)
            ->assertDontSee('vé điện tử')
            ->assertDontSee('check-in');
    }

    public function test_booking_qr_and_physical_artifacts_remain_separate_with_exact_format_wording(): void
    {
        $customer = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $customer->id)->booking;
        $payment = $this->pendingPayment($booking, ['amount' => (int) $booking->total_amount]);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);

        $this->actingAs($customer)->get(route('user.bookings.ticket', $booking->fresh()))
            ->assertOk()
            ->assertSee('QR ĐƠN ĐẶT VÉ')
            ->assertSee('Định dạng trình chiếu')
            ->assertDontSee('vé điện tử')
            ->assertDontSee('check-in');

        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)
            ->withSession([CinemaAccessService::SESSION_KEY => $scenario['cinema']->id])
            ->get(route('staff.tickets.operations', $booking))->assertOk()
            ->assertSee('Vé xem phim theo ghế')
            ->assertSee('Vé vật lý')
            ->assertDontSee('data-check-in-action', false);
    }

    public function test_promotion_surfaces_use_one_promotion_wording_and_truthful_type_fields(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.discounts.index'))
            ->assertOk()
            ->assertSee('Chưa có khuyến mãi')
            ->assertSee(route('admin.discounts.create'), false)
            ->assertDontSee('mã giảm giá');

        $response = $this->get(route('admin.discounts.create'))->assertOk()
            ->assertSee('Mỗi đơn đặt vé áp dụng tối đa một khuyến mãi')
            ->assertSee('Số tiền giảm (VNĐ)')
            ->assertSee('Tỷ lệ giảm (%)')
            ->assertSee('Mức giảm tối đa (VNĐ)')
            ->assertSee('Tính trên tổng tiền vé + đồ ăn trước khuyến mãi');
        $this->assertMatchesRegularExpression('/data-percentage-field[^>]*hidden/', $response->getContent());

        $scenario = $this->bookingScenario(false);
        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();
        $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'terminology@example.test',
            'skip_food' => 1,
        ])->assertRedirect(route('user.bookings.review'));
        $this->get(route('user.bookings.review'))->assertOk()
            ->assertSee('Khuyến mãi')
            ->assertSee('Mỗi đơn đặt vé áp dụng tối đa một khuyến mãi')
            ->assertDontSee('Mã giảm giá');
    }

    public function test_manager_catalog_empty_state_never_offers_global_food_mutation(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(route('admin.foods.index'))->assertOk()
            ->assertSee('Chưa có món ăn trong danh mục dùng chung')
            ->assertSee('Liên hệ Global Admin')
            ->assertDontSee('Hãy thêm món mới')
            ->assertDontSee(route('admin.foods.create'), false);
    }

    public function test_room_empty_state_dimensions_capacity_taxonomy_and_hierarchy_are_truthful(): void
    {
        $scenario = $this->bookingScenario(false, [[
            'x_position' => 4,
            'y_position' => 2,
            'cell_type' => 'blocked',
        ]]);
        $scenario['showtime']->delete();
        $manager = $this->userWithRole('manager');
        $session = [CinemaAccessService::SESSION_KEY => $scenario['cinema']->id];

        $this->actingAs($manager)->withSession($session)
            ->get(route('admin.rooms.show', $scenario['room']))->assertOk()
            ->assertSee('Chưa có suất chiếu sắp tới cho phòng này')
            ->assertSee(route('admin.showtimes.create'), false)
            ->assertSee('Kích thước phòng')
            ->assertSee('8,00 m × 10,00 m')
            ->assertSee('Lưới logic')
            ->assertSee('Sức chứa vật lý')
            ->assertSee('Vật cản cố định không phải là ghế')
            ->assertDontSee('Ô BLOCKED');

        $this->get(route('admin.rooms.layout.show', $scenario['room']))->assertOk()
            ->assertSee(route('admin.rooms.show', $scenario['room']), false)
            ->assertSee('Vật cản cố định')
            ->assertSee('Ô trống');
        $this->get(route('admin.rooms.seat-maintenance.index', $scenario['room']))->assertOk()
            ->assertSee(route('admin.rooms.show', $scenario['room']), false)
            ->assertSee('Bảo trì ghế');
    }

    public function test_counter_shows_room_type_and_exact_presentation_format_independently(): void
    {
        $scenario = $this->bookingScenario(false);
        $roomType = RoomType::query()->create([
            'code' => 'IMAX_RUNTIME_TEST',
            'name' => 'IMAX vận hành',
            'is_active' => true,
            'status' => true,
            'sort_order' => 90,
        ]);
        $scenario['room']->forceFill([
            'room_type' => $roomType->code,
            'room_type_id' => $roomType->id,
        ])->save();
        $scenario['showtime']->presentationFormat->update(['name' => '3D chính xác']);
        $scenario['showtime']->update([
            'show_date' => now($scenario['cinema']->timezone)->toDateString(),
            'show_time' => '23:59:00',
        ]);
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->withSession([CinemaAccessService::SESSION_KEY => $scenario['cinema']->id])
            ->get(route('staff.counter.index', ['date' => $scenario['showtime']->show_date->toDateString()]))
            ->assertOk()
            ->assertSee('Loại phòng')
            ->assertSee('IMAX vận hành')
            ->assertSee('Định dạng trình chiếu')
            ->assertSee('3D chính xác')
            ->assertDontSee('IMAX_RUNTIME_TEST');

        $this->withSession([CinemaAccessService::SESSION_KEY => $scenario['cinema']->id])
            ->get(route('staff.dashboard'))->assertOk()
            ->assertSee('Loại phòng: IMAX vận hành')
            ->assertSee('Định dạng trình chiếu: 3D chính xác')
            ->assertDontSee('IMAX_RUNTIME_TEST');
    }

    public function test_manager_price_book_empty_state_and_preview_use_business_vocabulary(): void
    {
        $this->chainPriceBook();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(route('admin.price-books.index'))->assertOk()
            ->assertSee('Hiện chưa có bảng giá đã phát hành trong phạm vi chi nhánh hiện tại')
            ->assertSee('Liên hệ Global Admin')
            ->assertSee('Định dạng trình chiếu không ảnh hưởng giá')
            ->assertDontSee('PresentationFormat')
            ->assertDontSee('Tạo bảng giá');
    }

    public function test_showtime_without_bookings_keeps_booking_not_attendance_language(): void
    {
        $scenario = $this->bookingScenario(false);
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $scenario['cinema']->id])
            ->get(route('admin.showtimes.show', $scenario['showtime']))->assertOk()
            ->assertSee('Chưa có đơn đặt vé cho suất chiếu này')
            ->assertDontSee('Chưa có khán giả')
            ->assertDontSee('attendance');
    }

    public function test_missing_published_layout_error_is_human_readable_and_non_technical(): void
    {
        $scenario = $this->bookingScenario(false);
        $room = Room::factory()->create([
            'cinema_id' => $scenario['cinema']->id,
            'room_type' => $scenario['room']->room_type,
            'room_type_id' => $scenario['room']->room_type_id,
            'width_mm' => 8_000,
            'length_mm' => 10_000,
            'status' => 'active',
        ]);
        $room->presentationCapabilities()->attach($scenario['showtime']->presentation_format_id);
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.showtimes.store'), [
            'movie_id' => $scenario['movie']->id,
            'presentation_format_id' => $scenario['showtime']->presentation_format_id,
            'room_id' => $room->id,
            'show_date' => now()->addDays(7)->toDateString(),
            'show_time' => '21:00',
            'status' => 'active',
        ])->assertSessionHasErrors([
            'room_id' => 'Phòng phải có sơ đồ đã phát hành trước khi xếp lịch. Hãy phát hành sơ đồ hợp lệ rồi thử lại.',
        ]);

        $message = $response->getSession()->get('errors')->first('room_id');
        $this->assertStringNotContainsString('SQLSTATE', $message);
        $this->assertStringNotContainsString('Exception', $message);
        $this->assertStringNotContainsString('layout published', $message);
    }
}
