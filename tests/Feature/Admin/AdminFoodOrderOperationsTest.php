<?php

namespace Tests\Feature\Admin;

use App\Models\Cinema;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\CinemaAccessService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Payments\PaymentTestCase;

final class AdminFoodOrderOperationsTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_index_only_lists_successful_orders_with_authoritative_payment_evidence(): void
    {
        $scenario = $this->bookingScenario(false);
        $successful = $this->foodOrder($scenario, 'FOOD-SUCCESS', 'paid', true, 45_000);
        $cancelled = $this->foodOrder($scenario, 'FOOD-CANCELLED', 'cancelled', false, 55_000);
        $pending = $this->foodOrder($scenario, 'FOOD-PENDING', 'pending', false, 35_000);
        $withoutEvidence = $this->foodOrder($scenario, 'FOOD-NO-EVIDENCE', 'paid', false, 25_000);
        $wrongPickup = $this->foodOrder($scenario, 'FOOD-WRONG-PICKUP', 'paid', true, 20_000);
        $wrongPickup->forceFill(['pickup_cinema_id' => null])->save();

        $response = $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.food-orders.index'))
            ->assertOk()
            ->assertSee('Chỉ đơn thành công')
            ->assertSee('Đơn thành công')
            ->assertSee('Số phần món')
            ->assertSee('Doanh thu đồ ăn')
            ->assertSee('Phiếu chưa in')
            ->assertSee($successful->booking->booking_code)
            ->assertSee('45.000 VNĐ')
            ->assertSee('Đã thanh toán')
            ->assertDontSee('Đã hủy');

        foreach ([$cancelled, $pending, $withoutEvidence, $wrongPickup] as $hidden) {
            $response->assertDontSee($hidden->booking->booking_code);
        }
    }

    public function test_search_and_voucher_filters_are_server_side_and_preserve_success_scope(): void
    {
        $scenario = $this->bookingScenario(false);
        $unprinted = $this->foodOrder($scenario, 'FOOD-UNPRINTED', 'paid', true, 30_000);
        $printed = $this->foodOrder($scenario, 'FOOD-PRINTED', 'paid', true, 40_000);
        $printed->booking->foodPickupVoucher->forceFill([
            'print_count' => 1,
            'last_printed_at' => now(),
        ])->save();

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get(route('admin.food-orders.index', [
            'search' => $unprinted->booking->foodPickupVoucher->voucher_code,
        ]))->assertOk()
            ->assertSee($unprinted->booking->booking_code)
            ->assertDontSee($printed->booking->booking_code);

        $this->actingAs($manager)->get(route('admin.food-orders.index', [
            'voucher_status' => 'printed',
        ]))->assertOk()
            ->assertSee($printed->booking->booking_code)
            ->assertDontSee($unprinted->booking->booking_code)
            ->assertSee('Đã in 1 lần');
    }

    public function test_detail_uses_food_snapshots_and_links_payment_booking_and_print_workspace(): void
    {
        $scenario = $this->bookingScenario(false);
        $order = $this->foodOrder($scenario, 'FOOD-DETAIL', 'paid', true, 42_000);
        $item = $order->items->sole();
        $payment = $order->booking->payments()->where('status', Payment::STATUS_SUCCESS)->sole();
        $item->food->update(['name' => 'Tên menu đã thay đổi']);

        $response = $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.food-orders.show', $order))
            ->assertOk()
            ->assertSee('Đơn đủ điều kiện phục vụ')
            ->assertSee('Bằng chứng thanh toán')
            ->assertSee('Tên snapshot FOOD-DETAIL')
            ->assertDontSee('Tên menu đã thay đổi')
            ->assertSee('42.000 VNĐ')
            ->assertSee('80.000 VND')
            ->assertSee('Mở quầy in')
            ->assertSee(route('staff.tickets.operations', $order->booking), false)
            ->assertSee(route('admin.bookings.show', $order->booking), false)
            ->assertSee(route('admin.payments.show', $payment), false);

        $response->assertDontSee($order->customer_phone);
        $response->assertDontSee($order->customer_email);
    }

    public function test_unsuccessful_detail_is_not_exposed_and_invalid_filters_are_rejected(): void
    {
        $scenario = $this->bookingScenario(false);
        $cancelled = $this->foodOrder($scenario, 'FOOD-HIDDEN-DETAIL', 'cancelled', false, 20_000);
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->get(route('admin.food-orders.show', $cancelled))
            ->assertNotFound();

        $this->actingAs($manager)->get(route('admin.food-orders.index', [
            'voucher_status' => 'cancelled',
            'date_from' => '2026-08-20',
            'date_to' => '2026-08-19',
            'sort' => 'status',
            'direction' => 'sideways',
            'per_page' => 999,
        ]))->assertSessionHasErrors(['voucher_status', 'date_to', 'sort', 'direction', 'per_page']);
    }

    public function test_manager_is_server_side_scoped_while_global_admin_can_view_chain_wide(): void
    {
        $scenario = $this->bookingScenario(false);
        $local = $this->foodOrder($scenario, 'FOOD-LOCAL-BRANCH', 'paid', true, 32_000);
        $foreign = $this->foodOrder($scenario, 'FOOD-FOREIGN-BRANCH', 'paid', true, 36_000);
        $foreignCinema = Cinema::query()->create([
            'code' => 'FOOD-FOREIGN',
            'name' => 'Food Foreign Branch',
            'address' => '99 Foreign Street',
            'city' => 'Ha Noi',
            'canonical_key' => 'food-foreign-branch',
            'status' => 'active',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        $foreign->booking->forceFill(['cinema_id' => $foreignCinema->id])->save();
        $foreign->forceFill(['pickup_cinema_id' => $foreignCinema->id])->save();

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get(route('admin.food-orders.index', [
            'cinema_id' => $foreignCinema->id,
        ]))->assertOk()
            ->assertSee($local->booking->booking_code)
            ->assertDontSee($foreign->booking->booking_code);
        $this->actingAs($manager)->get(route('admin.food-orders.show', $foreign))->assertNotFound();

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->withSession([CinemaAccessService::SESSION_KEY => 'all'])
            ->get(route('admin.food-orders.index'))
            ->assertOk()
            ->assertSee($local->booking->booking_code)
            ->assertSee($foreign->booking->booking_code);
        $this->actingAs($admin)->withSession([CinemaAccessService::SESSION_KEY => 'all'])
            ->get(route('admin.food-orders.show', $foreign))
            ->assertOk();
    }

    public function test_index_query_count_stays_bounded_as_successful_orders_grow(): void
    {
        $scenario = $this->bookingScenario(false);
        $this->foodOrder($scenario, 'FOOD-QUERY-ONE', 'paid', true, 20_000);
        $manager = $this->userWithRole('manager');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($manager)->get(route('admin.food-orders.index'))->assertOk();
        $oneOrderQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        for ($index = 0; $index < 8; $index++) {
            $this->foodOrder($scenario, 'FOOD-QUERY-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'paid', true, 20_000 + $index);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($manager)->get(route('admin.food-orders.index'))->assertOk();
        $manyOrderQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($oneOrderQueries + 2, $manyOrderQueries, 'Danh sách đơn đồ ăn có dấu hiệu N+1.');
    }

    private function foodOrder(array $scenario, string $bookingCode, string $status, bool $withEvidence, int $foodTotal): Order
    {
        $bookingStatus = in_array($status, ['paid', 'completed'], true) ? 'paid' : ($status === 'cancelled' ? 'cancelled' : 'pending_payment');
        $paymentStatus = in_array($status, ['paid', 'completed'], true) ? 'paid' : 'unpaid';
        $booking = $this->bookingForScenario($scenario, [
            'booking_code' => $bookingCode,
            'customer_name' => 'Khách '.$bookingCode,
            'customer_phone' => '0901234567',
            'customer_email' => strtolower($bookingCode).'@example.test',
            'booking_status' => $bookingStatus,
            'payment_status' => $paymentStatus,
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
            'food_subtotal' => $foodTotal,
            'total_amount' => 80_000,
        ]);
        $order = Order::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'pickup_cinema_id' => $booking->cinema_id,
            'subtotal' => $foodTotal,
            'total_amount' => $foodTotal,
            'status' => $status,
        ]);
        $food = FoodItem::query()->create([
            'name' => 'Tên menu '.$bookingCode,
            'price' => $foodTotal,
            'active' => true,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'food_item_id' => $food->id,
            'quantity' => 1,
            'snapshot_name' => 'Tên snapshot '.$bookingCode,
            'unit_price' => $foodTotal,
            'line_total' => $foodTotal,
            'price' => $foodTotal,
            'total' => $foodTotal,
        ]);

        if ($withEvidence) {
            $this->pendingPayment($booking, [
                'status' => Payment::STATUS_SUCCESS,
                'verified_at' => now(),
                'paid_at' => now(),
                'amount' => 80_000,
            ]);
        }

        return $order->fresh(['booking.foodPickupVoucher', 'items.food']);
    }
}
