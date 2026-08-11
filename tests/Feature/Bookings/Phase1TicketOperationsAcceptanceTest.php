<?php

namespace Tests\Feature\Bookings;

use App\Models\AdmissionTicket;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\TicketCheckinEvent;
use App\Models\UserCinemaAssignment;
use App\Services\Tickets\TicketCheckinCapability;
use App\Services\Tickets\TicketCheckinService;
use App\Services\Tickets\TicketPrintService;
use App\Services\Tickets\TicketQrPayload;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Payments\PaymentTestCase;

final class Phase1TicketOperationsAcceptanceTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_each_physical_seat_has_one_ticket_without_changing_commercial_pricing(): void
    {
        $normalScenario = $this->bookingScenario(true);
        $normalSeats = $normalScenario['seats'];
        $normalSeats->slice(2)->each(fn ($seat) => $seat->forceFill([
            'type' => 'normal', 'status' => 'active', 'pair_code' => null, 'pair_position' => null,
        ])->save());
        $threeNormalIds = collect([$normalSeats[0]->id, $normalSeats[2]->id, $normalSeats[3]->id])->all();
        $threeNormals = $this->paidBooking($normalScenario, $threeNormalIds);
        $this->assertSame(3, $threeNormals->admissionTickets()->count());
        $this->assertSame(3, $threeNormals->bookingSeats()->count());

        $coupleScenario = $this->bookingScenario(true);
        $pair = $coupleScenario['seats']->where('type', 'couple')->values();
        $couple = $this->paidBooking($coupleScenario, $pair->pluck('id')->all());
        $this->assertSame(2, $couple->admissionTickets()->count());
        $this->assertSame(1, $couple->bookingSeats()->distinct()->count('pricing_unit_key'));
        $this->assertSame(
            (int) $couple->seat_subtotal,
            (int) $couple->bookingSeats()->select('pricing_unit_key', 'final_unit_amount')->distinct()->sum('final_unit_amount'),
        );

        $mixedScenario = $this->bookingScenario(true);
        $mixedSeats = collect([$mixedScenario['seats'][0]])->merge($mixedScenario['seats']->where('type', 'couple'));
        $mixed = $this->paidBooking($mixedScenario, $mixedSeats->pluck('id')->all());
        $totalBeforeArtifacts = (int) $mixed->total_amount;
        $this->assertSame(3, $mixed->admissionTickets()->count());
        $this->assertSame($totalBeforeArtifacts, (int) $mixed->fresh()->total_amount);
        $this->assertSame($totalBeforeArtifacts, (int) $mixed->authoritativePayment->amount);

        $vipScenario = $this->bookingScenario(false);
        $vipScenario['seats'][0]->update(['type' => 'vip']);
        $vip = $this->paidBooking($vipScenario, [$vipScenario['seats'][0]->id]);
        $this->assertSame(1, $vip->admissionTickets()->count());
    }

    public function test_food_booking_has_exactly_one_pickup_voucher_and_booking_without_food_has_none(): void
    {
        $withFoodScenario = $this->bookingScenario(false);
        $booking = $this->reserve($withFoodScenario, [$withFoodScenario['seats'][0]->id])->booking;
        $food = FoodItem::query()->create([
            'cinema_id' => $withFoodScenario['cinema']->id,
            'name' => 'Combo Couple', 'price' => 90000, 'active' => true,
        ]);
        $order = Order::query()->create([
            'booking_id' => $booking->id,
            'customer_name' => 'Khách MovieMate',
            'customer_email' => 'guest@example.test',
            'pickup_cinema_id' => $booking->cinema_id,
            'subtotal' => 90000,
            'total_amount' => 90000,
            'status' => 'pending',
        ]);
        $order->items()->create([
            'food_item_id' => $food->id,
            'quantity' => 1,
            'snapshot_name' => 'Combo Couple',
            'unit_price' => 90000,
            'line_total' => 90000,
            'price' => 90000,
            'total' => 90000,
        ]);
        $withFood = $this->verifyBooking($booking);
        $this->assertSame(1, $withFood->foodPickupVoucher()->count());
        $this->assertSame(1, $withFood->foodPickupVoucher->booking->foodOrder->items()->count());

        $withoutFoodScenario = $this->bookingScenario(false);
        $withoutFood = $this->paidBooking($withoutFoodScenario, [$withoutFoodScenario['seats'][0]->id]);
        $this->assertSame(0, $withoutFood->foodPickupVoucher()->count());
    }

    public function test_first_print_and_reasoned_reprint_are_audited_without_marking_ticket_used(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->paidBooking($scenario, [$scenario['seats'][0]->id]);
        $ticket = $booking->admissionTickets()->sole();
        $staff = $this->userWithRole('staff');
        $prints = app(TicketPrintService::class);
        $firstId = (string) Str::uuid();
        $firstToken = Str::random(64);

        $prints->start($ticket, $staff, $firstId, $firstToken);
        $prints->succeed($ticket, $staff, $firstId, $firstToken);
        $ticket->refresh();
        $this->assertSame(1, $ticket->print_count);
        $this->assertNull($ticket->used_at);

        $this->actingAs($staff)->post(route('staff.admission-tickets.print.reprint', $ticket), [])
            ->assertSessionHasErrors('reason_code');
        $this->assertSame(1, $ticket->fresh()->print_count);

        $this->post(route('staff.admission-tickets.print.reprint', $ticket), ['reason_code' => 'paper_jam'])
            ->assertRedirect(route('staff.admission-tickets.print.show', $ticket));
        $this->post(route('staff.admission-tickets.print.succeed', $ticket))
            ->assertRedirect(route('staff.tickets.operations', $booking));
        $ticket->refresh();
        $this->assertSame(2, $ticket->print_count);
        $this->assertNull($ticket->used_at);
        $this->assertDatabaseHas('booking_ticket_print_events', [
            'admission_ticket_id' => $ticket->id,
            'event_type' => 'reprint_requested',
            'failure_code' => 'paper_jam',
        ]);
    }

    public function test_door_lookup_is_read_only_and_each_ticket_can_be_admitted_exactly_once(): void
    {
        $scenario = $this->bookingScenario(true);
        $pair = $scenario['seats']->where('type', 'couple')->values();
        $booking = $this->paidBooking($scenario, $pair->pluck('id')->all());
        $tickets = $booking->admissionTickets()->with('bookingSeat.seat')->orderBy('id')->get();
        $staff = $this->userWithRole('staff');
        $payload = app(TicketQrPayload::class)->url($tickets[0]);

        $this->actingAs($staff)->post(route('staff.tickets.consume'), ['ticket' => $payload])
            ->assertRedirect(route('staff.tickets.check'))->assertSessionHas('ticket_lookup.status', 'unused');
        $this->assertNull($tickets[0]->fresh()->used_at);

        $this->post(route('staff.admission-tickets.admit', $tickets[0]))
            ->assertSessionHas('checkin_result.result', 'accepted');
        $firstUsedAt = $tickets[0]->fresh()->getRawOriginal('used_at');
        $this->assertNotNull($firstUsedAt);
        $this->assertNull($tickets[1]->fresh()->used_at);

        $this->post(route('staff.admission-tickets.admit', $tickets[0]))
            ->assertSessionHas('checkin_result.result', 'already_used');
        $this->assertSame($firstUsedAt, $tickets[0]->fresh()->getRawOriginal('used_at'));

        $this->post(route('staff.admission-tickets.admit', $tickets[1]))
            ->assertSessionHas('checkin_result.result', 'accepted');
        $this->assertNotNull($tickets[1]->fresh()->used_at);
        $this->assertSame(2, TicketCheckinEvent::query()->where('result', 'accepted')->count());
    }

    public function test_branch_invalid_cancelled_and_food_voucher_inputs_are_rejected(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->paidBooking($scenario, [$scenario['seats'][0]->id]);
        $ticket = $booking->admissionTickets()->sole();
        $staff = $this->userWithRole('staff');
        UserCinemaAssignment::query()->where('user_id', $staff->id)->update(['status' => 'revoked']);
        $this->actingAs($staff)->post(route('staff.admission-tickets.admit', $ticket))->assertNotFound();
        $this->post(route('staff.admission-tickets.print.start', $ticket))->assertNotFound();
        $this->assertNull($ticket->fresh()->used_at);

        $authorized = $this->userWithRole('staff');
        $booking->forceFill(['booking_status' => 'cancelled'])->save();
        $this->actingAs($authorized)->post(route('staff.admission-tickets.admit', $ticket))
            ->assertSessionHas('checkin_result.result', 'cancelled');
        $this->post(route('staff.tickets.consume'), ['ticket' => 'AT-'.str_repeat('Z', 26)])->assertNotFound();

        $foodScenario = $this->bookingScenario(false);
        $foodBooking = $this->bookingWithFood($foodScenario);
        $this->actingAs($authorized)->post(route('staff.tickets.consume'), [
            'ticket' => $foodBooking->foodPickupVoucher->voucher_code,
        ])->assertNotFound();
    }

    public function test_used_ticket_reprint_preserves_used_state(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->paidBooking($scenario, [$scenario['seats'][0]->id]);
        $ticket = $booking->admissionTickets()->sole();
        $staff = $this->userWithRole('staff');
        $prints = app(TicketPrintService::class);
        $operation = (string) Str::uuid();
        $token = Str::random(64);
        $prints->start($ticket, $staff, $operation, $token);
        $prints->succeed($ticket, $staff, $operation, $token);
        app(TicketCheckinService::class)->checkIn(app(TicketCheckinCapability::class)->issue($ticket), $staff);
        $usedAt = $ticket->fresh()->getRawOriginal('used_at');

        $reprint = (string) Str::uuid();
        $reprintToken = Str::random(64);
        $prints->reprint($ticket->fresh(), $staff, $reprint, $reprintToken, 'faded_ticket', null);
        $prints->succeed($ticket->fresh(), $staff, $reprint, $reprintToken);

        $this->assertSame($usedAt, $ticket->fresh()->getRawOriginal('used_at'));
        $this->assertSame(2, $ticket->fresh()->print_count);
    }

    public function test_schema_enforces_ticket_voucher_and_accepted_checkin_cardinality(): void
    {
        $this->assertTrue(Schema::hasTable('admission_tickets'));
        $this->assertTrue(Schema::hasTable('food_pickup_vouchers'));
        $this->assertTrue(collect(Schema::getIndexes('admission_tickets'))->contains(fn ($index) => $index['columns'] === ['booking_seat_id'] && $index['unique']));
        $this->assertTrue(collect(Schema::getIndexes('food_pickup_vouchers'))->contains(fn ($index) => $index['columns'] === ['booking_id'] && $index['unique']));
        $this->assertTrue(collect(Schema::getIndexes('ticket_checkin_events'))->contains(fn ($index) => $index['columns'] === ['accepted_ticket_id'] && $index['unique']));

        $scenario = $this->bookingScenario(false);
        $booking = $this->paidBooking($scenario, [$scenario['seats'][0]->id]);
        $ticket = $booking->admissionTickets()->sole();
        $this->expectException(QueryException::class);
        AdmissionTicket::query()->create([
            'booking_id' => $booking->id,
            'booking_seat_id' => $ticket->booking_seat_id,
            'ticket_code' => 'AT-'.str_repeat('X', 26),
        ]);
    }

    public function test_database_allows_at_most_one_accepted_event_for_a_ticket(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->paidBooking($scenario, [$scenario['seats'][0]->id]);
        $ticket = $booking->admissionTickets()->sole();
        TicketCheckinEvent::query()->create([
            'admission_ticket_id' => $ticket->id,
            'accepted_ticket_id' => $ticket->id,
            'booking_id' => $booking->id,
            'showtime_id' => $booking->showtime_id,
            'result' => TicketCheckinEvent::RESULT_ACCEPTED,
            'scanned_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        TicketCheckinEvent::query()->create([
            'admission_ticket_id' => $ticket->id,
            'accepted_ticket_id' => $ticket->id,
            'booking_id' => $booking->id,
            'showtime_id' => $booking->showtime_id,
            'result' => TicketCheckinEvent::RESULT_ACCEPTED,
            'scanned_at' => now(),
        ]);
    }

    public function test_phase_one_views_have_bounded_queries_for_multiple_tickets(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(true);
        $booking = $this->reserve($scenario, $scenario['seats']->where('status', 'active')->pluck('id')->all(), $owner->id)->booking;
        $booking = $this->verifyBooking($booking);
        $staff = $this->userWithRole('staff');

        $customerQueries = $this->queryCount(fn () => $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))->assertOk());
        $staffQueries = $this->queryCount(fn () => $this->actingAs($staff)->get(route('staff.tickets.operations', $booking))->assertOk());
        $this->assertLessThanOrEqual(30, $customerQueries);
        $this->assertLessThanOrEqual(35, $staffQueries);
    }

    private function paidBooking(array $scenario, array $seatIds)
    {
        return $this->verifyBooking($this->reserve($scenario, $seatIds)->booking);
    }

    private function verifyBooking($booking)
    {
        $payment = $this->pendingPayment($booking, ['amount' => (int) $booking->total_amount]);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);

        return $booking->fresh()->load(['admissionTickets.bookingSeat.seat', 'foodPickupVoucher', 'authoritativePayment']);
    }

    private function bookingWithFood(array $scenario)
    {
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;
        $food = FoodItem::query()->create(['cinema_id' => $scenario['cinema']->id, 'name' => 'Coca', 'price' => 30000, 'active' => true]);
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

    private function queryCount(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
