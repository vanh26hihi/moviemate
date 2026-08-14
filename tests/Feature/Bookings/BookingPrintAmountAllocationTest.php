<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\FoodItem;
use App\Models\Payment;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use App\Services\PromotionService;
use App\Services\Tickets\BookingPrintAmountAllocator;
use App\Services\Tickets\TicketArtifactProvisioner;
use App\Services\ZeroPayableBookingSettlement;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Feature\Payments\PaymentTestCase;

final class BookingPrintAmountAllocationTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_no_promotion_preserves_80k_ticket_and_55k_food_snapshots_on_physical_documents(): void
    {
        [$booking] = $this->paidBooking(seatAmount: 80_000, foodAmount: 55_000);
        $amounts = app(BookingPrintAmountAllocator::class)->allocate($booking);
        $ticket = $booking->admissionTickets->sole();

        $this->assertSame(80_000, $amounts->forTicket($ticket));
        $this->assertSame(55_000, $amounts->foodVoucherAmount);
        $this->assertSame(135_000, $amounts->allocatedTotal());

        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.admission-tickets.print.start', $ticket))->assertRedirect();
        $this->get(route('staff.admission-tickets.print.show', $ticket))
            ->assertOk()
            ->assertSee('VÉ VÀO PHÒNG CHIẾU PHIM')
            ->assertSee('CINEMA TICKET')
            ->assertSee('80.000 VNĐ')
            ->assertSee($booking->showtime->room->name)
            ->assertDontSee('Cổng thanh toán');

        $this->post(route('staff.food-pickup-vouchers.print', $booking->foodPickupVoucher))
            ->assertOk()
            ->assertSee('PHIẾU NHẬN ĐỒ ĂN')
            ->assertSee('FOOD PICKUP VOUCHER')
            ->assertSee('55.000 VNĐ')
            ->assertSee('Thức ăn kiểm thử')
            ->assertSee('× 1');
    }

    public function test_fixed_promotion_print_all_uses_exact_largest_remainder_allocation(): void
    {
        [$booking] = $this->paidBooking(seatAmount: 80_000, foodAmount: 55_000, discountType: 'fixed', discountValue: 20_000);
        $amounts = app(BookingPrintAmountAllocator::class)->allocate($booking);

        $this->assertSame(115_000, (int) $booking->total_amount);
        $this->assertSame(68_148, $amounts->forTicket($booking->admissionTickets->sole()));
        $this->assertSame(46_852, $amounts->foodVoucherAmount);
        $this->assertSame(115_000, $amounts->allocatedTotal());

        $response = $this->actingAs($this->userWithRole('staff'))
            ->post(route('staff.tickets.print-all', $booking))->assertOk();
        $response->assertSee('68.148 VNĐ')->assertSee('46.852 VNĐ');
    }

    public function test_multi_seat_and_food_allocations_sum_to_final_payable(): void
    {
        $scenario = $this->bookingScenario(true);
        $scenario['seats'][1]->update(['status' => 'active']);
        [$booking] = $this->paidBooking(
            seatAmount: 80_000,
            foodAmount: 40_000,
            discountType: 'fixed',
            discountValue: 50_000,
            scenario: $scenario,
            seatIds: $scenario['seats']->take(2)->pluck('id')->all(),
        );
        $amounts = app(BookingPrintAmountAllocator::class)->allocate($booking);

        $this->assertSame([32_143, 32_143], array_values($amounts->ticketAmounts));
        $this->assertSame(25_714, $amounts->foodVoucherAmount);
        $this->assertSame(90_000, $amounts->allocatedTotal());
        $this->assertSame((int) $booking->total_amount, $amounts->allocatedTotal());
    }

    public function test_percentage_promotion_allocates_exactly_in_integer_vnd(): void
    {
        [$booking] = $this->paidBooking(seatAmount: 80_000, foodAmount: 55_000, discountType: 'percent', discountValue: 10);
        $amounts = app(BookingPrintAmountAllocator::class)->allocate($booking);

        $this->assertSame(121_500, (int) $booking->total_amount);
        $this->assertSame(72_000, $amounts->forTicket($booking->admissionTickets->sole()));
        $this->assertSame(49_500, $amounts->foodVoucherAmount);
        $this->assertSame(121_500, $amounts->allocatedTotal());
    }

    public function test_zero_payable_allocates_zero_to_every_artifact_without_external_payment(): void
    {
        [$booking] = $this->pendingBooking(seatAmount: 80_000, foodAmount: 20_000, discountType: 'fixed', discountValue: 1_000_000);
        $payment = app(ZeroPayableBookingSettlement::class)->settle($booking);
        $booking = $this->loadBooking($booking->fresh());
        $amounts = app(BookingPrintAmountAllocator::class)->allocate($booking);

        $this->assertSame(Payment::PROVIDER_INTERNAL_ZERO, $payment->provider);
        $this->assertSame(0, $payment->amount);
        $this->assertSame([0], array_values($amounts->ticketAmounts));
        $this->assertSame(0, $amounts->foodVoucherAmount);
        $this->assertSame(0, $amounts->allocatedTotal());
        $this->assertDatabaseMissing('payments', ['booking_id' => $booking->id, 'provider' => 'zalopay']);
    }

    public function test_couple_pair_produces_two_tickets_but_allocates_one_pricing_unit_once(): void
    {
        $scenario = $this->bookingScenario(true);
        $pair = $scenario['seats']->where('type', 'couple');
        [$booking] = $this->paidBooking(scenario: $scenario, seatIds: $pair->pluck('id')->all());
        $amounts = app(BookingPrintAmountAllocator::class)->allocate($booking);

        $this->assertSame(2, $booking->admissionTickets->count());
        $this->assertSame(1, $booking->bookingSeats->unique('pricing_unit_key')->count());
        $this->assertSame(100_000, (int) $booking->seat_subtotal);
        $this->assertSame([50_000, 50_000], array_values($amounts->ticketAmounts));
        $this->assertSame(100_000, array_sum($amounts->ticketAmounts));
    }

    public function test_odd_couple_pricing_and_rounding_remain_deterministic_without_double_charge(): void
    {
        $scenario = $this->bookingScenario(true);
        $pair = $scenario['seats']->where('type', 'couple');
        [$booking] = $this->paidBooking(
            seatAmount: 100_001,
            discountType: 'fixed',
            discountValue: 1,
            scenario: $scenario,
            seatIds: $pair->pluck('id')->all(),
        );
        $amounts = app(BookingPrintAmountAllocator::class)->allocate($booking);

        $this->assertSame(2, $booking->admissionTickets->count());
        $this->assertSame(100_000, (int) $booking->seat_subtotal);
        $this->assertSame(99_999, (int) $booking->total_amount);
        $this->assertSame([50_000, 49_999], array_values($amounts->ticketAmounts));
        $this->assertSame(99_999, $amounts->allocatedTotal());
    }

    public function test_allocator_rejects_inconsistent_finalized_food_item_snapshot(): void
    {
        [$booking] = $this->paidBooking(seatAmount: 80_000, foodAmount: 55_000);
        $booking->foodOrder->items->sole()->forceFill(['line_total' => 54_999])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('food item snapshot');

        app(BookingPrintAmountAllocator::class)->allocate($booking->fresh());
    }

    public function test_print_all_rolls_back_every_print_audit_when_commercial_snapshot_is_invalid(): void
    {
        [$booking] = $this->paidBooking(seatAmount: 80_000, foodAmount: 55_000);
        $booking->forceFill(['total_amount' => 134_999])->save();
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->userWithRole('staff'))->post(route('staff.tickets.print-all', $booking));
            $this->fail('The inconsistent commercial snapshot should reject Print All.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('commercial snapshot', $exception->getMessage());
        }

        $this->assertDatabaseCount('booking_ticket_prints', 0);
        $this->assertDatabaseCount('booking_ticket_print_events', 0);
        $this->assertDatabaseCount('food_pickup_voucher_print_events', 0);
        $this->assertSame([0], $booking->admissionTickets()->pluck('print_count')->all());
        $this->assertSame(0, $booking->foodPickupVoucher()->sole()->print_count);
    }

    public function test_food_voucher_print_rolls_back_its_audit_when_commercial_snapshot_is_invalid(): void
    {
        [$booking] = $this->paidBooking(seatAmount: 80_000, foodAmount: 55_000);
        $booking->forceFill(['total_amount' => 134_999])->save();
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->userWithRole('staff'))
                ->post(route('staff.food-pickup-vouchers.print', $booking->foodPickupVoucher));
            $this->fail('The inconsistent commercial snapshot should reject voucher printing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('commercial snapshot', $exception->getMessage());
        }

        $this->assertDatabaseCount('food_pickup_voucher_print_events', 0);
        $this->assertSame(0, $booking->foodPickupVoucher()->sole()->print_count);
    }

    public function test_reprint_amount_is_stable_after_current_prices_and_promotion_configuration_change(): void
    {
        [$booking, $discount, $food] = $this->paidBooking(
            seatAmount: 80_000,
            foodAmount: 55_000,
            discountType: 'fixed',
            discountValue: 20_000,
        );
        $ticket = $booking->admissionTickets->sole();
        $before = app(BookingPrintAmountAllocator::class)->allocate($booking)->forTicket($ticket);

        $food?->update(['price' => 888_888]);
        $discount?->update(['discount_value' => 1]);
        $after = app(BookingPrintAmountAllocator::class)->allocate($booking->fresh())->forTicket($ticket);

        $this->assertSame(68_148, $before);
        $this->assertSame($before, $after);

        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.admission-tickets.print.start', $ticket))->assertRedirect();
        $this->get(route('staff.admission-tickets.print.show', $ticket))->assertOk()->assertSee('68.148 VNĐ');
        $this->post(route('staff.admission-tickets.print.succeed', $ticket))->assertRedirect();
        $this->post(route('staff.admission-tickets.print.reprint', $ticket), ['reason_code' => 'paper_jam'])->assertRedirect();
        $this->get(route('staff.admission-tickets.print.show', $ticket))->assertOk()->assertSee('68.148 VNĐ');
    }

    public function test_promotion_change_before_payment_does_not_change_booking_snapshot_or_provider_amount(): void
    {
        [$booking, $discount] = $this->pendingBooking(
            seatAmount: 80_000,
            foodAmount: 55_000,
            discountType: 'fixed',
            discountValue: 20_000,
        );
        $discount?->update(['discount_value' => 1]);
        $booking = $this->settleExternally($booking);
        $amounts = app(BookingPrintAmountAllocator::class)->allocate($booking);

        $this->assertSame(20_000, $booking->promotion_discount_amount);
        $this->assertSame(115_000, (int) $booking->total_amount);
        $this->assertSame(115_000, $booking->payments->sole()->amount);
        $this->assertSame(115_000, $amounts->allocatedTotal());
    }

    private function paidBooking(
        int $seatAmount = 50_000,
        ?int $foodAmount = null,
        ?string $discountType = null,
        ?int $discountValue = null,
        ?array $scenario = null,
        ?array $seatIds = null,
    ): array {
        [$booking, $discount, $food] = $this->pendingBooking(
            $seatAmount,
            $foodAmount,
            $discountType,
            $discountValue,
            $scenario,
            $seatIds,
        );

        return [$this->settleExternally($booking), $discount, $food];
    }

    private function pendingBooking(
        int $seatAmount = 50_000,
        ?int $foodAmount = null,
        ?string $discountType = null,
        ?int $discountValue = null,
        ?array $scenario = null,
        ?array $seatIds = null,
    ): array {
        $scenario ??= $this->bookingScenario(true, basePrice: $seatAmount);

        $food = $foodAmount === null ? null : FoodItem::query()->create([
            'cinema_id' => $scenario['cinema']->id,
            'name' => 'Thức ăn kiểm thử',
            'price' => $foodAmount,
            'active' => true,
        ]);
        $discount = $discountType === null ? null : DiscountCode::query()->create([
            'code' => 'PRINT'.Str::upper(Str::random(8)),
            'name' => 'Khuyến mãi bản in',
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
        ]);
        $booking = app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            $seatIds ?? [$scenario['seats'][0]->id],
            null,
            'print-allocation@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
            $food ? [['food_id' => $food->id, 'quantity' => 1]] : [],
            discountCodes: $discount ? [$discount->code] : [],
        )->booking;

        return [$booking, $discount, $food];
    }

    private function settleExternally(Booking $booking): Booking
    {
        $payment = $this->pendingPayment($booking, ['amount' => (int) $booking->total_amount]);
        $payment->forceFill([
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
        ])->save();
        $booking->forceFill([
            'payment_method' => 'zalopay',
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
            'expires_at' => null,
        ])->save();
        $booking->foodOrder()->update(['status' => 'paid']);
        app(PromotionService::class)->redeem($booking);
        app(TicketArtifactProvisioner::class)->provision($booking->fresh());

        return $this->loadBooking($booking->fresh());
    }

    private function loadBooking(Booking $booking): Booking
    {
        return $booking->load([
            'payments',
            'bookingSeats.seat',
            'admissionTickets.bookingSeat.seat',
            'foodOrder.items',
            'foodPickupVoucher',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room',
        ]);
    }
}
