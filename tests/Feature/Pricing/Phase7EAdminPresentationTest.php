<?php

namespace Tests\Feature\Pricing;

use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class Phase7EAdminPresentationTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_fixed_and_percentage_forms_expose_only_their_authoritative_fields(): void
    {
        $admin = $this->userWithRole('admin');
        $fixed = $this->promotion('FIXED-UX');
        $percentage = $this->promotion('PERCENT-UX', [
            'type' => Promotion::TYPE_PERCENTAGE,
            'discount_amount_vnd' => null,
            'discount_percent' => 15,
            'maximum_discount_vnd' => 30_000,
        ]);

        $this->actingAs($admin)->get(route('admin.discounts.edit', $fixed))
            ->assertOk()
            ->assertSee('Số tiền giảm (VNĐ)')
            ->assertDontSee(' name="discount_percent"', false)
            ->assertDontSee(' name="maximum_discount_vnd"', false)
            ->assertSee('Đơn tối thiểu được tính trên tổng tiền vé + đồ ăn trước khuyến mãi.')
            ->assertSee('Mỗi đơn đặt vé áp dụng tối đa một khuyến mãi.')
            ->assertSee('Tại đúng thời điểm kết thúc, khuyến mãi không còn hiệu lực.');

        $this->actingAs($admin)->get(route('admin.discounts.edit', ['discount' => $percentage->getKey()]))
            ->assertOk()
            ->assertSee(' name="discount_percent"', false)
            ->assertSee(' name="maximum_discount_vnd"', false)
            ->assertDontSee(' name="discount_amount_vnd"', false)
            ->assertSee('Để trống nếu không đặt giới hạn riêng.')
            ->assertSee('Giá trị 0 không có nghĩa là không giới hạn.');
    }

    public function test_released_usage_keeps_promotion_locked_in_form_and_index(): void
    {
        $scenario = $this->bookingScenario(false);
        $promotion = $this->promotion('RELEASED-LOCK');
        $booking = $this->bookingForScenario($scenario);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking(
            $booking,
            $promotion->code,
            100_000,
        ));
        app(PromotionService::class)->release($booking);

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('admin.discounts.edit', $promotion))
            ->assertOk()
            ->assertSee('Đã phát sinh sử dụng — nội dung khuyến mãi đã được khóa.')
            ->assertSee('kể cả lượt đã giải phóng')
            ->assertSee('name="is_active"', false)
            ->assertSee('disabled', false);

        $this->get(route('admin.discounts.index'))
            ->assertOk()
            ->assertSee($promotion->code)
            ->assertSee('Đã phát sinh sử dụng · nội dung đã khóa');
    }

    public function test_existing_showtime_displays_dynamic_frozen_snapshot_and_source_version(): void
    {
        $scenario = $this->bookingScenario(false);
        $showtime = $scenario['showtime']->fresh('ticketPrices.seatType');
        $versionNumber = data_get($showtime->ticketPrices->first()->breakdown_json, 'version_number');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.showtimes.edit', $showtime))
            ->assertOk()
            ->assertSee('Giá đã khóa cho suất chiếu')
            ->assertSee('Nguồn bảng giá: v'.$versionNumber)
            ->assertDontSee('Giá hiện tại:')
            ->assertSee($showtime->ticketPrices->first()->seatType->name)
            ->assertSee(number_format($showtime->ticketPrices->first()->final_unit_amount_vnd, 0, ',', '.').' VNĐ');
    }

    private function promotion(string $code, array $overrides = []): Promotion
    {
        return Promotion::query()->create([
            'code' => $code,
            'name' => 'Promotion '.$code,
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 20_000,
            'discount_percent' => null,
            'maximum_discount_vnd' => null,
            'minimum_order_vnd' => 0,
            'is_active' => true,
            'registered_users_only' => false,
            'first_order_only' => false,
            ...$overrides,
        ]);
    }
}
