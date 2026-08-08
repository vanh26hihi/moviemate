<?php

namespace Tests\Feature\Promotions;

use App\Models\Cinema;
use App\Models\DiscountCode;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DiscountCodeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_combinable_codes_apply_by_priority_to_remaining_subtotal(): void
    {
        $cinema = Cinema::factory()->create();
        DiscountCode::query()->create(['code' => ' fixed10 ', 'name' => 'Fixed', 'discount_type' => 'fixed', 'discount_value' => 10000, 'can_combine' => true, 'priority' => 20]);
        DiscountCode::query()->create(['code' => 'percent10', 'name' => 'Percent', 'discount_type' => 'percent', 'discount_value' => 10, 'can_combine' => true, 'priority' => 10]);

        $quote = app(PromotionService::class)->quote(100000, ['percent10', 'FIXED10'], null, $cinema->id);

        $this->assertSame(19000, $quote->discountAmount);
        $this->assertSame(81000, $quote->finalAmount);
        $this->assertSame(['FIXED10', 'PERCENT10'], $quote->lines->pluck('code.code')->all());
    }

    public function test_discount_never_exceeds_remaining_subtotal_and_percent_cap_is_honoured(): void
    {
        $cinema = Cinema::factory()->create();
        DiscountCode::query()->create(['code' => 'CAP', 'name' => 'Cap', 'discount_type' => 'percent', 'discount_value' => 90, 'maximum_discount_amount' => 15000]);
        $quote = app(PromotionService::class)->quote(100000, ['cap'], null, $cinema->id);
        $this->assertSame(15000, $quote->discountAmount);

        DiscountCode::query()->create(['code' => 'OVER', 'name' => 'Over', 'discount_type' => 'fixed', 'discount_value' => 999999]);
        $quote = app(PromotionService::class)->quote(100000, ['over'], null, $cinema->id);
        $this->assertSame(0, $quote->finalAmount);
    }

    public function test_registered_first_order_and_branch_rules_are_server_authoritative(): void
    {
        $cinema = Cinema::factory()->create();
        $other = Cinema::factory()->create();
        $user = User::factory()->create();
        $code = DiscountCode::query()->create(['code' => 'MEMBER', 'name' => 'Member', 'discount_type' => 'fixed', 'discount_value' => 1000, 'registered_users_only' => true]);
        $code->cinemas()->sync([$cinema->id]);

        foreach ([[null, $cinema->id], [$user->id, $other->id]] as [$userId, $cinemaId]) {
            try {
                app(PromotionService::class)->quote(50000, ['MEMBER'], $userId, $cinemaId);
                $this->fail('Expected an eligibility error.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('discount_code', $exception->errors());
            }
        }
    }

    public function test_non_combinable_and_maximum_code_rules_are_rejected(): void
    {
        config(['promotions.max_discount_codes_per_booking' => 1]);
        $cinema = Cinema::factory()->create();
        foreach (['ONE', 'TWO'] as $code) {
            DiscountCode::query()->create(['code' => $code, 'name' => $code, 'discount_type' => 'fixed', 'discount_value' => 100]);
        }
        $this->expectException(ValidationException::class);
        app(PromotionService::class)->quote(50000, ['ONE', 'TWO'], null, $cinema->id);
    }
}
