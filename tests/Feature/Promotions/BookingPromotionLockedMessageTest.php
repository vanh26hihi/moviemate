<?php

namespace Tests\Feature\Promotions;

use App\Models\Promotion;
use Tests\Feature\Payments\VnpayPaymentTestCase;

final class BookingPromotionLockedMessageTest extends VnpayPaymentTestCase
{
    public function test_customer_receives_an_actionable_message_when_a_payment_attempt_has_locked_promotions(): void
    {
        $scenario = $this->bookingScenario(false);
        Promotion::query()->create([
            'code' => 'WELCOME20K',
            'name' => 'Khuyến mãi chào mừng',
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 20_000,
            'minimum_order_vnd' => 0,
        ]);

        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();
        $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'promotion-lock@example.test',
            'food_items' => [],
        ])->assertRedirect(route('user.bookings.review'));
        $this->post(route('user.bookings.confirm'), [
            'payment_method' => 'vnpay',
        ])->assertRedirectContains('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');

        $response = $this->from(route('user.bookings.review'))->post(route('user.bookings.promotions'), [
            'action' => 'apply',
            'code' => 'WELCOME20K',
        ]);

        $response
            ->assertRedirect(route('user.bookings.review'))
            ->assertSessionHasErrors([
                'discount_code' => 'Không thể áp dụng hoặc đổi mã khuyến mãi vì một giao dịch thanh toán đã được tạo cho đơn này. Để tránh sai lệch số tiền với cổng thanh toán, khuyến mãi phải được giữ nguyên. Hãy tiếp tục giao dịch đang chờ; nếu muốn dùng mã khác, hãy hủy đơn hiện tại và đặt lại.',
            ]);
    }
}
