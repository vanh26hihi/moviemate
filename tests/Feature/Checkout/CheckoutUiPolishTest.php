<?php

namespace Tests\Feature\Checkout;

use App\Models\FoodItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use Tests\Feature\Payments\PaymentTestCase;

class CheckoutUiPolishTest extends PaymentTestCase
{
    public function test_seat_food_and_review_views_share_mobile_safe_four_step_progress(): void
    {
        $scenario = $this->bookingScenario();
        $vip = Seat::query()->create([
            'room_id' => $scenario['room']->id,
            'row' => 'C',
            'number' => 1,
            'seat_code' => 'C1',
            'type' => 'vip',
            'status' => 'active',
        ]);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $scenario['layout']->id,
            'x_position' => 1,
            'y_position' => 2,
            'cell_type' => 'seat',
            'seat_id' => $vip->id,
        ]);

        $this->get(route('user.bookings.selectSeat', $scenario['showtime']))
            ->assertOk()
            ->assertSee('grid grid-cols-4', false)
            ->assertSee('overflow-x-auto', false)
            ->assertSee('Thường')
            ->assertSee('VIP')
            ->assertSee('Ghế đôi')
            ->assertSee('Bảo trì')
            ->assertSee('data-pair-code="B-PAIR-1"', false)
            ->assertSee('Tiếp tục chọn đồ ăn');

        $activeFood = $this->food('Combo đang bán', 35_000, true);
        $this->food('Combo đã ẩn', 25_000, false);

        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))
            ->assertOk()
            ->assertSee('aria-current="step"', false)
            ->assertSee($activeFood->name)
            ->assertDontSee('Combo đã ẩn')
            ->assertSee('max="20"', false)
            ->assertSee('data-quantity-decrease', false)
            ->assertSee('data-quantity-increase', false)
            ->assertSee('Bỏ qua đồ ăn')
            ->assertDontSee('name="total_amount"', false);

        $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'mobile@example.test',
            'skip_food' => 1,
        ])->assertRedirect(route('user.bookings.review'));

        $this->get(route('user.bookings.review'))
            ->assertOk()
            ->assertSee('Xác nhận đơn đặt vé')
            ->assertSee($scenario['movie']->title)
            ->assertSee($scenario['room']->name)
            ->assertSee('Ghế <strong>A1</strong> · Thường', false)
            ->assertSee('50.000 VND')
            ->assertSee('Không chọn đồ ăn')
            ->assertSee('ZaloPay')
            ->assertSee('mobile@example.test')
            ->assertSee('Thời gian phiên checkout còn lại')
            ->assertDontSee('name="total_amount"', false)
            ->assertDontSee('name="payment_status"', false);
    }

    public function test_each_non_paid_booking_state_has_its_own_message_and_no_ticket_action(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('user');
        $cases = [
            ['pending', Payment::STATUS_PENDING, 'pending_payment', 'unpaid', 'Đang chờ xác minh thanh toán', 'user.bookings.pending'],
            ['review', Payment::STATUS_REVIEW, 'pending_payment', 'unpaid', 'Giao dịch đang được đối soát', 'user.bookings.payment-review'],
            ['failed', Payment::STATUS_FAILED, 'pending_payment', 'unpaid', 'Thanh toán không thành công', 'user.bookings.failed'],
            ['expired', Payment::STATUS_EXPIRED, 'expired', 'unpaid', 'Booking đã hết hạn', 'user.bookings.expired'],
            ['cancelled', Payment::STATUS_PENDING, 'cancelled', 'unpaid', 'Booking đã bị hủy', 'user.bookings.success'],
            ['used', Payment::STATUS_SUCCESS, 'used', 'paid', 'Vé đã được sử dụng', 'user.bookings.success'],
        ];

        foreach ($cases as [$state, $paymentState, $bookingState, $bookingPaymentState, $message, $routeName]) {
            $scenario = $this->bookingScenario(false);
            $result = $this->reserve($scenario, [$scenario['seats'][0]->id], $user->id);
            $booking = $result->booking;
            $booking->forceFill([
                'booking_status' => $bookingState,
                'payment_status' => $bookingPaymentState,
            ])->save();
            $this->pendingPayment($booking, ['status' => $paymentState]);

            $this->actingAs($user)->get(route($routeName, $booking))
                ->assertOk()
                ->assertSee('data-booking-state="'.$state.'"', false)
                ->assertSee($message)
                ->assertDontSee('data-paid-ticket-link', false)
                ->assertDontSee('data-qr-value', false)
                ->assertDontSee('data-ticket-download', false);
        }
    }

    public function test_paid_booking_alone_exposes_a_complete_local_ticket(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $result = $this->reserve($scenario, [$scenario['seats'][0]->id], $user->id);
        $booking = $result->booking;
        $booking->forceFill([
            'booking_status' => 'paid',
            'payment_status' => 'paid',
            'seat_subtotal' => 50_000,
            'food_subtotal' => 40_000,
            'total_amount' => 90_000,
            'currency' => 'VND',
            'paid_at' => now(),
        ])->save();
        $this->pendingPayment($booking, [
            'status' => Payment::STATUS_SUCCESS,
            'amount' => 90_000,
            'verified_at' => now(),
            'paid_at' => now(),
        ]);
        $order = Order::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $booking->customer_email,
            'pickup_cinema_id' => $scenario['cinema']->id,
            'subtotal' => 40_000,
            'total_amount' => 40_000,
            'status' => 'paid',
        ]);
        $order->items()->create([
            'food_item_id' => $this->food('Bắp rang caramel', 40_000)->id,
            'quantity' => 1,
            'snapshot_name' => 'Bắp rang caramel',
            'unit_price' => 40_000,
            'line_total' => 40_000,
            'price' => 40_000,
            'total' => 40_000,
        ]);

        $this->actingAs($user)->get(route('user.bookings.success', $booking))
            ->assertOk()
            ->assertSee('data-booking-state="paid"', false)
            ->assertSee('data-paid-ticket-link', false)
            ->assertSee($booking->booking_code)
            ->assertSee('Bắp rang caramel')
            ->assertSee('90.000 VND')
            ->assertDontSee('api.qrserver.com', false);

        $this->actingAs($user)->get(route('user.bookings.ticket', $booking))
            ->assertOk()
            ->assertSee('data-ticket-state="usable"', false)
            ->assertSee('data-qr-value="'.$booking->booking_code.'"', false)
            ->assertSee('data-ticket-download', false)
            ->assertSee('A1')
            ->assertSee('Bắp rang caramel')
            ->assertSee('90.000 VND')
            ->assertDontSee('api.qrserver.com', false);
    }

    private function food(string $name, int $price, bool $active = true): FoodItem
    {
        return FoodItem::query()->create([
            'name' => $name,
            'price' => $price,
            'active' => $active,
        ]);
    }
}
