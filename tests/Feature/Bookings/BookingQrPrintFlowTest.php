<?php

namespace Tests\Feature\Bookings;

use App\Models\FoodItem;
use App\Models\Order;
use App\Models\UserCinemaAssignment;
use App\Services\Tickets\BookingQrPayload;
use App\Services\Tickets\TicketPrintService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Payments\PaymentTestCase;

final class BookingQrPrintFlowTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_customer_receives_exactly_one_booking_qr_for_a_multi_seat_booking(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(true);
        $booking = $this->verifyBooking($this->reserve(
            $scenario,
            $scenario['seats']->where('type', 'couple')->pluck('id')->all(),
            $owner->id,
        )->booking);

        $response = $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))->assertOk();
        $payload = app(BookingQrPayload::class)->value($booking);

        $response->assertSee('data-qr-value="'.$payload.'"', false)
            ->assertSee('QR ĐƠN ĐẶT VÉ')
            ->assertSee('Vui lòng xuất trình mã đơn hoặc QR tại quầy để nhận vé.')
            ->assertDontSee('QR riêng cho ghế');
        $this->assertSame(1, substr_count($response->getContent(), 'data-qr-value='));
        $this->assertSame(2, $booking->admissionTickets()->count());
        $this->assertSame(1, $booking->bookingSeats()->distinct()->count('pricing_unit_key'));
        $this->assertSame(
            (int) $booking->seat_subtotal,
            (int) $booking->bookingSeats()->select('pricing_unit_key', 'final_unit_amount')->distinct()->sum('final_unit_amount'),
        );
    }

    public function test_staff_resolves_secure_booking_qr_or_booking_code_with_branch_scope(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->verifyBooking($this->reserve($scenario, [$scenario['seats'][0]->id])->booking);
        $staff = $this->userWithRole('staff');
        $payload = app(BookingQrPayload::class)->value($booking);

        $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => $payload])
            ->assertOk()->assertSee($booking->booking_code);
        $this->post(route('staff.tickets.resolve'), ['ticket' => $booking->booking_code])
            ->assertOk()->assertSee($booking->booking_code);
        $this->post(route('staff.tickets.resolve'), ['ticket' => substr($payload, 0, -1).'X'])
            ->assertNotFound();

        UserCinemaAssignment::query()->where('user_id', $staff->id)->update(['status' => 'revoked']);
        $this->post(route('staff.tickets.resolve'), ['ticket' => $payload])->assertNotFound();
    }

    public function test_food_booking_has_one_voucher_and_booking_without_food_has_none(): void
    {
        $withFood = $this->bookingWithFood($this->bookingScenario(false));
        $this->assertSame(1, $withFood->foodPickupVoucher()->count());

        $scenario = $this->bookingScenario(false);
        $withoutFood = $this->verifyBooking($this->reserve($scenario, [$scenario['seats'][0]->id])->booking);
        $this->assertSame(0, $withoutFood->foodPickupVoucher()->count());
    }

    public function test_first_print_needs_no_reason_and_reprint_requires_a_reason(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->verifyBooking($this->reserve($scenario, [$scenario['seats'][0]->id])->booking);
        $ticket = $booking->admissionTickets()->sole();
        $staff = $this->userWithRole('staff');
        $prints = app(TicketPrintService::class);
        $operation = (string) Str::uuid();
        $token = Str::random(64);

        $prints->start($ticket, $staff, $operation, $token);
        $prints->succeed($ticket, $staff, $operation, $token);
        $this->assertSame(1, $ticket->fresh()->print_count);

        $this->actingAs($staff)->post(route('staff.admission-tickets.print.reprint', $ticket), [])
            ->assertSessionHasErrors('reason_code');
        $this->post(route('staff.admission-tickets.print.reprint', $ticket), ['reason_code' => 'paper_jam'])
            ->assertRedirect(route('staff.admission-tickets.print.show', $ticket));
        $this->post(route('staff.admission-tickets.print.succeed', $ticket))
            ->assertRedirect(route('staff.tickets.operations', $booking));

        $this->assertSame(2, $ticket->fresh()->print_count);
        $this->assertDatabaseHas('booking_ticket_print_events', [
            'admission_ticket_id' => $ticket->id,
            'event_type' => 'reprint_requested',
            'failure_code' => 'paper_jam',
        ]);
    }

    public function test_print_all_prepares_each_ticket_and_the_single_food_voucher(): void
    {
        $scenario = $this->bookingScenario(true);
        $booking = $this->bookingWithFood($scenario, $scenario['seats']->where('type', 'couple')->pluck('id')->all());
        $staff = $this->userWithRole('staff');

        $response = $this->actingAs($staff)->post(route('staff.tickets.print-all', $booking))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'data-print-artifact="ticket"'));
        $this->assertSame(1, substr_count($response->getContent(), 'data-print-artifact="food-voucher"'));
        $this->assertSame([1, 1], $booking->admissionTickets()->orderBy('id')->pluck('print_count')->all());
        $this->assertSame(1, $booking->foodPickupVoucher()->value('print_count'));

        $this->post(route('staff.tickets.print-all', $booking))->assertStatus(409);
    }

    public function test_final_schema_and_routes_have_no_digital_checkin_contract(): void
    {
        $this->assertTrue(Schema::hasTable('admission_tickets'));
        $this->assertTrue(Schema::hasTable('food_pickup_vouchers'));
        $this->assertFalse(Schema::hasTable('ticket_checkin_events'));
        $this->assertFalse(Schema::hasColumn('admission_tickets', 'used_at'));
        $this->assertFalse(Schema::hasColumn('admission_tickets', 'used_by_user_id'));
        $this->assertFalse(Schema::hasColumn('bookings', 'used_at'));
        $this->assertFalse(app('router')->has('tickets.verify'));
        $this->assertFalse(app('router')->has('staff.tickets.check'));
        $this->assertFalse(app('router')->has('staff.tickets.consume'));
        $this->assertFalse(app('router')->has('staff.admission-tickets.admit'));
    }

    private function verifyBooking($booking)
    {
        $payment = $this->pendingPayment($booking, ['amount' => (int) $booking->total_amount]);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);

        return $booking->fresh()->load(['admissionTickets.bookingSeat.seat', 'foodPickupVoucher', 'authoritativePayment']);
    }

    private function bookingWithFood(array $scenario, ?array $seatIds = null)
    {
        $booking = $this->reserve($scenario, $seatIds ?? [$scenario['seats'][0]->id])->booking;
        $food = FoodItem::query()->create([
            'cinema_id' => $scenario['cinema']->id, 'name' => 'Coca', 'price' => 30000, 'active' => true,
        ]);
        $order = Order::query()->create([
            'booking_id' => $booking->id, 'customer_name' => 'Khách', 'customer_email' => 'guest@example.test',
            'pickup_cinema_id' => $booking->cinema_id, 'subtotal' => 30000, 'total_amount' => 30000, 'status' => 'pending',
        ]);
        $order->items()->create([
            'food_item_id' => $food->id, 'quantity' => 1, 'snapshot_name' => 'Coca',
            'unit_price' => 30000, 'line_total' => 30000, 'price' => 30000, 'total' => 30000,
        ]);

        return $this->verifyBooking($booking);
    }
}
