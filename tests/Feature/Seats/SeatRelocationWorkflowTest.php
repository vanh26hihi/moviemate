<?php

namespace Tests\Feature\Seats;

use App\Mail\SeatRelocationMail;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\BookingTicketPrint;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\RoomType;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Models\SeatIncidentImpact;
use App\Models\SeatIncidentResolution;
use App\Models\SeatIncidentSeat;
use App\Models\Showtime;
use App\Models\UserCinemaAssignment;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use App\Services\CinemaContext;
use App\Services\Seats\SeatRelocationCandidateService;
use App\Services\Tickets\BookingQrPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\CreatesPriceBookFixtures;
use Tests\TestCase;

final class SeatRelocationWorkflowTest extends TestCase
{
    use CreatesPriceBookFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_resolution_schema_has_required_keys_indexes_and_uniqueness(): void
    {
        $indexes = collect(Schema::getIndexes('seat_incident_resolutions'));
        $this->assertTrue($indexes->contains(fn (array $index): bool => ($index['unique'] ?? false)
            && ($index['columns'] ?? []) === ['seat_incident_impact_id']));
        $this->assertTrue($indexes->contains('name', 'seat_incident_resolutions_operation_index'));
        $this->assertTrue($indexes->contains('name', 'seat_incident_resolutions_state_index'));
        $this->assertContains('seat_incident_resolution_id', collect(Schema::getColumns('booking_ticket_print_events'))->pluck('name'));
        $this->assertContains('active_seat_incident_resolution_id', collect(Schema::getColumns('booking_ticket_prints'))->pluck('name'));
        foreach (Schema::getForeignKeys('seat_incident_resolutions') as $foreign) {
            $this->assertNotSame('cascade', strtolower((string) ($foreign['on_delete'] ?? '')));
        }
    }

    public function test_equivalent_relocation_preserves_identity_commercial_state_and_notifies_after_commit(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        $bookingSeat = $scenario['bookingSeat'];
        $ticketId = $bookingSeat->admissionTicket->id;
        $snapshots = $this->commercialSnapshots($scenario['booking'], $bookingSeat, $scenario['payment']);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();

        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [
            $scenario['room'], $incident, $impact,
        ]), ['replacement_seat_id' => $scenario['seats']['A2']->id])->assertSessionHas('success');

        $this->assertSame($bookingSeat->id, $bookingSeat->fresh()->id);
        $this->assertSame($scenario['seats']['A2']->id, $bookingSeat->fresh()->seat_id);
        $this->assertSame($ticketId, $bookingSeat->admissionTicket->fresh()->id);
        $this->assertDatabaseHas('seat_incident_resolutions', [
            'seat_incident_impact_id' => $impact->id,
            'resolution_type' => 'equivalent',
            'original_seat_id' => $scenario['seats']['A1']->id,
            'replacement_seat_id' => $scenario['seats']['A2']->id,
            'reprint_required' => false,
        ]);
        $this->assertSame('resolved', $impact->fresh()->resolution_status);
        $this->assertSame('resolved', $incident->fresh()->status);
        $this->assertSame('maintenance', $scenario['seats']['A1']->fresh()->status);
        $this->assertCommercialSnapshots($snapshots, $scenario['booking'], $bookingSeat, $scenario['payment']);
        Mail::assertSent(SeatRelocationMail::class, fn (SeatRelocationMail $mail): bool => $mail->hasTo($scenario['booking']->customer_email)
            && $mail->relocations[0]['original'] === 'A1'
            && $mail->relocations[0]['replacement'] === 'A2'
        );
        $mailHtml = Mail::sent(SeatRelocationMail::class)->first()->render();
        $this->assertStringContainsString($scenario['booking']->booking_code, $mailHtml);
        $this->assertStringContainsString('Relocation Movie', $mailHtml);
        $this->assertStringContainsString('A1', $mailHtml);
        $this->assertStringContainsString('A2', $mailHtml);
        $this->assertStringContainsString('không phải thanh toán thêm', $mailHtml);
    }

    public function test_upgrade_is_allowed_only_after_equivalents_are_exhausted_and_never_reprices(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $options = app(SeatRelocationCandidateService::class)->forIncident($incident)[$impact->id];
        $this->assertSame(['A2', 'A3'], $options['equivalent']->pluck('label')->all());
        $this->assertSame(['B1'], $options['upgrade']->pluck('label')->all());

        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['B1']->id,
        ])->assertSessionHasErrors('replacement_seat_id');
        Seat::query()->whereIn('id', [$scenario['seats']['A2']->id, $scenario['seats']['A3']->id])->update(['status' => 'maintenance']);
        $snapshots = $this->commercialSnapshots($scenario['booking'], $scenario['bookingSeat'], $scenario['payment']);

        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['B1']->id,
        ])->assertSessionHas('success');

        $resolution = SeatIncidentResolution::query()->firstOrFail();
        $this->assertSame('upgrade', $resolution->resolution_type);
        $this->assertSame(50000, $resolution->original_pre_promotion_amount);
        $this->assertSame(70000, $resolution->replacement_hypothetical_amount);
        $this->assertCommercialSnapshots($snapshots, $scenario['booking'], $scenario['bookingSeat'], $scenario['payment']);
    }

    public function test_different_single_seat_type_uses_value_for_upgrade_without_mutating_commercial_state(): void
    {
        Mail::fake();
        $scenario = $this->scenario('B1');
        $scenario['bookingSeat']->forceFill([
            'price' => 40000, 'base_amount' => 40000, 'surcharge_total' => 0, 'final_unit_amount' => 40000,
        ])->save();
        $scenario['booking']->forceFill([
            'seat_subtotal' => 40000, 'food_subtotal' => 15000, 'gross_amount' => 55000,
            'promotion_discount_amount' => 5000, 'total_amount' => 50000,
        ])->save();
        $scenario['payment']->forceFill(['amount' => 50000])->save();
        $foodOrder = Order::query()->create([
            'booking_id' => $scenario['booking']->id,
            'customer_name' => 'Relocation Customer',
            'customer_email' => $scenario['booking']->customer_email,
            'pickup_cinema_id' => $scenario['cinema']->id,
            'subtotal' => 15000,
            'total_amount' => 15000,
            'status' => 'paid',
        ]);
        $snapshots = $this->commercialSnapshots($scenario['booking'], $scenario['bookingSeat'], $scenario['payment']);
        $foodSnapshot = $this->raw($foodOrder, ['booking_id', 'subtotal', 'total_amount', 'status']);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();

        $options = app(SeatRelocationCandidateService::class)->forIncident($incident)[$impact->id];
        $this->assertTrue($options['equivalent']->isEmpty());
        $this->assertSame(['A1', 'A2', 'A3'], $options['upgrade']->pluck('label')->all());
        $this->assertSame([50000, 50000, 50000], $options['upgrade']->pluck('hypothetical_amount')->all());

        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHas('success');

        $resolution = SeatIncidentResolution::query()->firstOrFail();
        $this->assertSame('upgrade', $resolution->resolution_type);
        $this->assertSame(40000, $resolution->original_pre_promotion_amount);
        $this->assertSame(50000, $resolution->replacement_hypothetical_amount);
        $this->assertCommercialSnapshots($snapshots, $scenario['booking'], $scenario['bookingSeat'], $scenario['payment']);
        $this->assertSame($foodSnapshot, $this->raw($foodOrder->fresh(), ['booking_id', 'subtotal', 'total_amount', 'status']));
    }

    public function test_downgrade_and_retained_payment_are_rejected_server_side(): void
    {
        $scenario = $this->scenario('B1', 'review');
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $before = $this->seatCommercial($scenario['bookingSeat']);

        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHasErrors('impact');
        $this->assertSame($before, $this->seatCommercial($scenario['bookingSeat']->fresh()));
        $this->assertDatabaseCount('seat_incident_resolutions', 0);

        $scenario['booking']->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid'])->save();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHasErrors('impact');
        $this->assertDatabaseCount('seat_incident_resolutions', 0);

        $scenario['payment']->forceFill(['status' => 'success', 'verified_at' => now(), 'paid_at' => now()])->save();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHasErrors('replacement_seat_id');
        $this->assertDatabaseCount('seat_incident_resolutions', 0);
    }

    public function test_same_showtime_snapshot_keeps_equivalent_and_upgrade_relocation_values_frozen(): void
    {
        $scenario = $this->scenario();
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $options = app(SeatRelocationCandidateService::class)->forIncident($incident)[$impact->id];
        $this->assertSame(['A2', 'A3'], $options['equivalent']->pluck('label')->all());
        $this->assertSame(['B1'], $options['upgrade']->pluck('label')->all());
    }

    public function test_stale_candidate_collision_preserves_both_bookings_without_history(): void
    {
        $scenario = $this->scenario();
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        app(SeatRelocationCandidateService::class)->forIncident($incident);
        $competitor = $this->booking($scenario['showtime'], 'COMPETE', 'pending_payment', 'unpaid');
        $competingSeat = $this->bookingSeat($competitor, $scenario['seats']['A2'], 50000);

        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHasErrors('replacement_seat_id');

        $this->assertSame($scenario['seats']['A1']->id, $scenario['bookingSeat']->fresh()->seat_id);
        $this->assertSame($scenario['seats']['A2']->id, $competingSeat->fresh()->seat_id);
        $this->assertDatabaseCount('seat_incident_resolutions', 0);
    }

    public function test_candidate_inventory_excludes_held_booked_maintenance_and_active_incident_seats(): void
    {
        $scenario = $this->scenario(includeIncidentSeat: true);
        $incidentSeat = $scenario['seats']['E1'];
        $held = $this->booking($scenario['showtime'], 'HELD', 'pending_payment', 'unpaid');
        $this->bookingSeat($held, $scenario['seats']['A2'], 50000);
        $booked = $this->booking($scenario['showtime'], 'BOOKED', 'paid', 'paid');
        $this->bookingSeat($booked, $scenario['seats']['A3'], 50000);
        $scenario['seats']['B1']->forceFill(['status' => 'maintenance'])->save();
        $otherIncident = SeatIncident::query()->create([
            'cinema_id' => $scenario['cinema']->id, 'room_id' => $scenario['room']->id,
            'reported_by_user_id' => $scenario['manager']->id, 'status' => 'open', 'reason' => 'seat_broken',
        ]);
        SeatIncidentSeat::query()->create([
            'seat_incident_id' => $otherIncident->id, 'seat_id' => $incidentSeat->id,
            'active_lock_key' => SeatIncidentSeat::ACTIVE_LOCK_KEY,
        ]);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();

        $options = app(SeatRelocationCandidateService::class)->forIncident($incident)[$impact->id];

        $this->assertTrue($options['equivalent']->isEmpty());
        $this->assertTrue($options['upgrade']->isEmpty());
    }

    public function test_candidate_inventory_excludes_wrong_room_and_unreferenced_showtime_layout(): void
    {
        $scenario = $this->scenario();
        $foreignRoom = Room::factory()->create([
            'cinema_id' => $scenario['cinema']->id, 'code' => 'R'.str()->random(6),
        ]);
        $foreignSeat = Seat::query()->create([
            'room_id' => $foreignRoom->id, 'row' => 'Z', 'number' => 1, 'seat_code' => 'Z1',
            'type' => 'normal', 'status' => 'active',
        ]);
        $otherLayout = RoomLayout::query()->create([
            'room_id' => $scenario['room']->id, 'version' => 2, 'name' => 'Other showtime layout',
            'rows' => 5, 'columns' => 8, 'status' => 'draft',
        ]);
        $otherLayoutSeat = Seat::query()->create([
            'room_id' => $scenario['room']->id, 'row' => 'E', 'number' => 2, 'seat_code' => 'E2',
            'type' => 'normal', 'status' => 'active',
        ]);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $otherLayout->id, 'x_position' => 2, 'y_position' => 5,
            'cell_type' => 'seat', 'seat_id' => $otherLayoutSeat->id,
        ]);
        $otherLayout->update(['status' => 'retired', 'published_at' => now()]);
        Showtime::query()->create([
            'movie_id' => $scenario['showtime']->movie_id, 'cinema_id' => $scenario['cinema']->id,
            'room_id' => $scenario['room']->id, 'room_layout_id' => $otherLayout->id,
            'presentation_format_id' => $scenario['showtime']->presentation_format_id,
            'show_date' => now()->addDays(4)->toDateString(), 'show_time' => '19:00:00',
            'price' => 50000, 'vip_price' => 70000, 'pricing_version' => 'cinema-pricing-v1', 'status' => 'active',
        ]);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $options = app(SeatRelocationCandidateService::class)->forIncident($incident)[$impact->id];

        $this->assertNotContains('E2', $options['equivalent']->pluck('label')->all());
        $this->assertNotContains('E2', $options['upgrade']->pluck('label')->all());
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $foreignSeat->id,
        ])->assertNotFound();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $otherLayoutSeat->id,
        ])->assertSessionHasErrors('replacement_seat_id');
        $this->assertSame($scenario['seats']['A1']->id, $scenario['bookingSeat']->fresh()->seat_id);
    }

    public function test_requires_refund_rechecks_candidates_and_keeps_paid_artifacts(): void
    {
        $scenario = $this->scenario();
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.requires-refund', [$scenario['room'], $incident, $impact]))
            ->assertSessionHasErrors('impact');

        Seat::query()->whereIn('id', collect($scenario['seats'])->except('A1')->pluck('id'))->update(['status' => 'maintenance']);
        $commercialBefore = $this->commercialSnapshots($scenario['booking'], $scenario['bookingSeat'], $scenario['payment']);
        $ticketId = $scenario['bookingSeat']->admissionTicket->id;
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.requires-refund', [$scenario['room'], $incident, $impact]))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('seat_incident_resolutions', ['resolution_type' => 'requires_refund', 'replacement_seat_id' => null]);
        $this->assertSame('unresolved', $impact->fresh()->resolution_status);
        $this->assertSame('open', $incident->fresh()->status);
        $this->assertCommercialSnapshots($commercialBefore, $scenario['booking'], $scenario['bookingSeat'], $scenario['payment']);
        $this->assertDatabaseHas('admission_tickets', ['id' => $ticketId]);
    }

    public function test_multi_seat_relocation_changes_only_affected_booking_seat(): void
    {
        $scenario = $this->scenario();
        $unaffected = $this->bookingSeat($scenario['booking'], $scenario['seats']['A3'], 50000);
        $scenario['booking']->forceFill(['seat_subtotal' => 100000, 'gross_amount' => 100000, 'total_amount' => 100000])->save();
        $scenario['payment']->forceFill(['amount' => 100000])->save();
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();

        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHas('success');

        $this->assertSame($scenario['seats']['A2']->id, $scenario['bookingSeat']->fresh()->seat_id);
        $this->assertSame($scenario['seats']['A3']->id, $unaffected->fresh()->seat_id);
        $this->assertDatabaseCount('admission_tickets', 2);
    }

    public function test_couple_relocation_moves_two_existing_rows_atomically_as_one_operation(): void
    {
        $scenario = $this->scenario('C1');
        $second = $this->bookingSeat($scenario['booking'], $scenario['seats']['C2'], 50000, 'couple:C', 100000);
        $scenario['booking']->forceFill(['seat_subtotal' => 100000, 'gross_amount' => 100000, 'total_amount' => 100000])->save();
        $scenario['payment']->forceFill(['amount' => 100000])->save();
        $firstIds = [$scenario['bookingSeat']->id, $second->id];
        $ticketIds = $scenario['booking']->admissionTickets()->pluck('id')->sort()->values()->all();
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->oldest('id')->firstOrFail();
        $options = app(SeatRelocationCandidateService::class)->forIncident($incident)[$impact->id];

        $this->assertSame([$scenario['seats']['D1']->id], $options['equivalent']->pluck('seat_id')->all());
        $this->assertTrue($options['upgrade']->isEmpty());
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHasErrors('replacement_seat_id');

        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['D1']->id,
        ])->assertSessionHas('success');

        $this->assertSame($firstIds, $scenario['booking']->bookingSeats()->pluck('id')->sort()->values()->all());
        $this->assertSame([$scenario['seats']['D1']->id, $scenario['seats']['D2']->id], $scenario['booking']->bookingSeats()->pluck('seat_id')->sort()->values()->all());
        $this->assertSame($ticketIds, $scenario['booking']->admissionTickets()->pluck('id')->sort()->values()->all());
        $this->assertSame(1, SeatIncidentResolution::query()->pluck('operation_id')->unique()->count());
        $this->assertSame(2, SeatIncidentResolution::query()->count());
    }

    public function test_resolution_is_idempotent_and_branch_scoped(): void
    {
        $scenario = $this->scenario();
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $foreignCinema = Cinema::factory()->create(['is_primary' => false]);
        $foreignManager = $this->userWithRole('manager');
        $foreignManager->cinemaAssignments()->delete();
        UserCinemaAssignment::query()->create([
            'user_id' => $foreignManager->id, 'cinema_id' => $foreignCinema->id,
            'status' => UserCinemaAssignment::STATUS_ACTIVE, 'assigned_at' => now(),
        ]);
        $this->actingAs($foreignManager)->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertForbidden();

        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHas('success');
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A3']->id,
        ])->assertSessionHasErrors('impact');
        $this->assertDatabaseCount('seat_incident_resolutions', 1);
    }

    public function test_printed_relocation_requires_server_authorized_reprint_and_resolves_after_success(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        $ticket = $scenario['bookingSeat']->admissionTicket;
        $this->markPrinted($ticket);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHas('success');
        $resolution = SeatIncidentResolution::query()->firstOrFail();
        $this->assertTrue($resolution->reprint_required);
        $this->assertNull($resolution->reprint_satisfied_at);
        $this->assertSame('unresolved', $impact->fresh()->resolution_status);
        $this->assertSame('open', $incident->fresh()->status);

        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->get(route('staff.tickets.operations', $scenario['booking']))
            ->assertOk()->assertSee('Cần in lại do đổi ghế');
        $this->post(route('staff.admission-tickets.print.incident-reprint', [$ticket, $resolution]))
            ->assertRedirect(route('staff.admission-tickets.print.show', $ticket));
        $this->get(route('staff.admission-tickets.print.show', $ticket))
            ->assertOk()
            ->assertSee('Định dạng')
            ->assertSee($scenario['booking']->showtime->presentationFormat->name);
        $state = BookingTicketPrint::query()->where('admission_ticket_id', $ticket->id)->firstOrFail();
        $this->assertSame($resolution->id, $state->active_seat_incident_resolution_id);
        $this->assertDatabaseHas('booking_ticket_print_events', [
            'admission_ticket_id' => $ticket->id,
            'seat_incident_resolution_id' => $resolution->id,
            'event_type' => 'incident_reprint_requested',
            'failure_code' => 'seat_incident_relocation',
        ]);
        $this->post(route('staff.admission-tickets.print.succeed', $ticket))->assertSessionHas('success');

        $this->assertSame(2, $ticket->fresh()->print_count);
        $this->assertNotNull($resolution->fresh()->reprint_satisfied_at);
        $this->assertSame('resolved', $impact->fresh()->resolution_status);
        $this->assertSame('resolved', $incident->fresh()->status);
        $this->assertDatabaseHas('booking_ticket_print_events', [
            'seat_incident_resolution_id' => $resolution->id,
            'event_type' => 'print_succeeded',
            'failure_code' => 'seat_incident_relocation',
        ]);
    }

    public function test_failed_incident_reprint_keeps_relocation_and_requirement_retryable(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        $ticket = $scenario['bookingSeat']->admissionTicket;
        $this->markPrinted($ticket);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ]);
        $resolution = SeatIncidentResolution::query()->firstOrFail();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.admission-tickets.print.incident-reprint', [$ticket, $resolution]));
        $this->post(route('staff.admission-tickets.print.fail', $ticket), ['failure_code' => 'paper_jam'])
            ->assertSessionHas('success');

        $this->assertSame($scenario['seats']['A2']->id, $scenario['bookingSeat']->fresh()->seat_id);
        $this->assertNull($resolution->fresh()->reprint_satisfied_at);
        $this->assertSame('unresolved', $impact->fresh()->resolution_status);
        $this->assertSame(1, $ticket->fresh()->print_count);
        $this->actingAs($staff)->post(route('staff.admission-tickets.print.incident-reprint', [$ticket, $resolution]))
            ->assertRedirect(route('staff.admission-tickets.print.show', $ticket));
    }

    public function test_fake_incident_reprint_and_generic_reason_bypass_are_rejected(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        $ticket = $scenario['bookingSeat']->admissionTicket;
        $this->markPrinted($ticket);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ]);
        $resolution = SeatIncidentResolution::query()->firstOrFail();
        $other = $this->booking($scenario['showtime'], 'OTHER', 'paid', 'paid');
        $otherSeat = $this->bookingSeat($other, $scenario['seats']['A3'], 50000);
        $otherPayment = Payment::createForProvider('vnpay', ['booking_id' => $other->id, 'payment_method' => 'vnpay', 'amount' => 50000, 'status' => 'success', 'verified_at' => now(), 'paid_at' => now()]);
        $this->markPrinted($otherSeat->admissionTicket);
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->post(route('staff.admission-tickets.print.incident-reprint', [$otherSeat->admissionTicket, $resolution]))
            ->assertStatus(409);
        $this->post(route('staff.admission-tickets.print.reprint', $ticket), ['reason_code' => 'customer_request'])
            ->assertStatus(409);
        $this->post(route('staff.admission-tickets.print.reprint', $otherSeat->admissionTicket), ['incident_reprint' => true])
            ->assertSessionHasErrors('reason_code');
        $this->assertSame(1, $otherSeat->admissionTicket->fresh()->print_count);
        $this->assertSame('success', $otherPayment->fresh()->status);
    }

    public function test_unprinted_relocation_remains_first_print_and_renders_current_seat(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        $ticket = $scenario['bookingSeat']->admissionTicket;
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ]);
        $this->assertFalse(SeatIncidentResolution::query()->firstOrFail()->reprint_required);
        $this->assertSame(0, $ticket->fresh()->print_count);

        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.admission-tickets.print.start', $ticket))
            ->assertRedirect(route('staff.admission-tickets.print.show', $ticket));
        $this->get(route('staff.admission-tickets.print.show', $ticket))
            ->assertOk()->assertSee('A2')->assertSee('50.000 VNĐ');
        $this->assertDatabaseMissing('booking_ticket_print_events', ['event_type' => 'reprint_requested']);
    }

    public function test_upgrade_replacement_ticket_keeps_promotion_allocated_paid_amount(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        $scenario['booking']->forceFill(['promotion_discount_amount' => 10000, 'total_amount' => 40000])->save();
        $scenario['payment']->forceFill(['amount' => 40000])->save();
        $ticket = $scenario['bookingSeat']->admissionTicket;
        $this->markPrinted($ticket);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        Seat::query()->whereIn('id', [$scenario['seats']['A2']->id, $scenario['seats']['A3']->id])->update(['status' => 'maintenance']);
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['B1']->id,
        ])->assertSessionHas('success');
        $resolution = SeatIncidentResolution::query()->firstOrFail();

        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.admission-tickets.print.incident-reprint', [$ticket, $resolution]));
        $this->get(route('staff.admission-tickets.print.show', $ticket))
            ->assertOk()->assertSee('B1')->assertSee('Ghế VIP')->assertSee('40.000 VNĐ')->assertDontSee('70.000 VNĐ');
        $this->assertSame(40000, (int) $scenario['booking']->fresh()->total_amount);
        $this->assertSame(40000, (int) $scenario['payment']->fresh()->amount);
        $this->assertSame(50000, (int) $scenario['bookingSeat']->fresh()->price);
    }

    public function test_couple_relocation_waits_only_for_members_whose_paper_was_printed(): void
    {
        Mail::fake();
        $scenario = $this->scenario('C1');
        $second = $this->bookingSeat($scenario['booking'], $scenario['seats']['C2'], 50000, 'couple:C', 100000);
        $scenario['booking']->forceFill(['seat_subtotal' => 100000, 'gross_amount' => 100000, 'total_amount' => 100000])->save();
        $scenario['payment']->forceFill(['amount' => 100000])->save();
        $printedTicket = $scenario['bookingSeat']->admissionTicket;
        $this->markPrinted($printedTicket);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->oldest('id')->firstOrFail();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['D1']->id,
        ])->assertSessionHas('success');

        $this->assertSame(1, SeatIncidentResolution::query()->where('reprint_required', true)->count());
        $this->assertSame(1, SeatIncidentResolution::query()->where('reprint_required', false)->count());
        $this->assertSame(1, SeatIncidentImpact::query()->where('resolution_status', 'unresolved')->count());
        $this->assertSame(1, SeatIncidentImpact::query()->where('resolution_status', 'resolved')->count());
        $this->assertSame(0, $second->admissionTicket->fresh()->print_count);
        $this->assertSame('open', $incident->fresh()->status);

        $resolution = SeatIncidentResolution::query()->where('reprint_required', true)->firstOrFail();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.admission-tickets.print.incident-reprint', [$printedTicket, $resolution]));
        $this->post(route('staff.admission-tickets.print.succeed', $printedTicket));
        $this->assertSame('resolved', $incident->fresh()->status);
    }

    public function test_customer_sees_current_seat_history_and_one_unchanged_booking_qr(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        $owner = $this->userWithRole('user');
        $scenario['booking']->forceFill(['user_id' => $owner->id])->save();
        $qrBefore = app(BookingQrPayload::class)->value($scenario['booking']);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ]);

        $html = $this->actingAs($owner)->get(route('user.bookings.ticket', $scenario['booking']))
            ->assertOk()->assertSee('Ghế A1 đã được đổi sang')->assertSee('A2')->assertSee('Bạn không phải thanh toán thêm.')
            ->getContent();
        $this->assertSame(1, substr_count($html, 'QR ĐƠN ĐẶT VÉ'));
        $this->assertSame($qrBefore, app(BookingQrPayload::class)->value($scenario['booking']->fresh()));
        $this->assertSame($scenario['seats']['A2']->id, $scenario['bookingSeat']->fresh()->seat_id);
    }

    public function test_manager_candidate_ui_and_staff_queue_surface_task_specific_actions(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        $ticket = $scenario['bookingSeat']->admissionTicket;
        $this->markPrinted($ticket);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $this->actingAs($scenario['manager'])->get(route('admin.rooms.seat-incidents.show', [$scenario['room'], $incident]))
            ->assertOk()->assertSee('TƯƠNG ĐƯƠNG')->assertSee('NÂNG HẠNG')->assertSee('A2')->assertSee('B1');
        $this->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ]);

        $this->actingAs($this->userWithRole('staff'))->get(route('staff.prints.index'))
            ->assertOk()->assertSee($scenario['booking']->booking_code)->assertSee('Cần in lại do đổi ghế');
    }

    public function test_smtp_failure_does_not_rollback_committed_relocation(): void
    {
        $scenario = $this->scenario();
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHas('success');

        $this->assertSame($scenario['seats']['A2']->id, $scenario['bookingSeat']->fresh()->seat_id);
        $this->assertDatabaseHas('seat_incident_resolutions', ['seat_incident_impact_id' => $impact->id]);
    }

    public function test_incident_relocation_bypasses_only_the_ordinary_customer_seat_gap_rule(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        try {
            app(BookingCheckoutService::class)->createPendingBooking(
                $scenario['showtime']->id,
                [$scenario['seats']['A2']->id],
                null,
                'gap-customer@example.test',
                app(BookingTokenService::class)->issueCheckoutToken(),
            );
            $this->fail('Ordinary checkout must retain the no-isolated-seat rule.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('seat_ids', $exception->errors());
        }

        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $this->actingAs($scenario['manager'])->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ])->assertSessionHas('success');
        $this->assertSame($scenario['seats']['A2']->id, $scenario['bookingSeat']->fresh()->seat_id);
    }

    public function test_candidate_customer_staff_and_history_queries_are_bounded(): void
    {
        Mail::fake();
        $scenario = $this->scenario();
        $owner = $this->userWithRole('user');
        $scenario['booking']->forceFill(['user_id' => $owner->id])->save();
        $this->markPrinted($scenario['bookingSeat']->admissionTicket);
        $incident = $this->incident($scenario);
        $impact = $incident->impacts()->firstOrFail();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $start = $queries;
        $this->actingAs($scenario['manager'])->get(route('admin.rooms.seat-incidents.show', [$scenario['room'], $incident]))->assertOk();
        $managerQueries = $queries - $start;
        $this->post(route('admin.rooms.seat-incidents.relocate', [$scenario['room'], $incident, $impact]), [
            'replacement_seat_id' => $scenario['seats']['A2']->id,
        ]);
        $start = $queries;
        $this->actingAs($owner)->get(route('user.bookings.ticket', $scenario['booking']))->assertOk();
        $customerQueries = $queries - $start;
        $start = $queries;
        $this->actingAs($this->userWithRole('staff'))->get(route('staff.tickets.operations', $scenario['booking']))->assertOk();
        $staffQueries = $queries - $start;

        $this->assertLessThanOrEqual(35, $managerQueries, "Manager candidate/history detail issued {$managerQueries} queries.");
        $this->assertLessThanOrEqual(25, $customerQueries, "Customer relocated booking issued {$customerQueries} queries.");
        $this->assertLessThanOrEqual(30, $staffQueries, "Staff replacement-print detail issued {$staffQueries} queries.");
    }

    private function scenario(
        string $originalCode = 'A1',
        string $paymentStatus = 'success',
        bool $includeIncidentSeat = false,
    ): array {
        $cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        $room = Room::factory()->create(['cinema_id' => $cinema->id, 'code' => 'R'.str()->random(6)]);
        $roomType = RoomType::query()->firstOrCreate(['code' => $room->room_type ?: '2D'], [
            'name' => '2D', 'slug' => '2d', 'is_active' => true, 'status' => true, 'sort_order' => 1,
        ]);
        $room->forceFill(['room_type' => $roomType->code, 'room_type_id' => $roomType->id])->save();
        $layout = RoomLayout::query()->create(['room_id' => $room->id, 'version' => 1, 'name' => 'Relocation', 'rows' => $includeIncidentSeat ? 5 : 4, 'columns' => 8, 'status' => 'draft']);
        $definitions = [
            ['A1', 'normal', null, null], ['A2', 'normal', null, null], ['A3', 'normal', null, null], ['B1', 'vip', null, null],
            ['C1', 'couple', 'C', 'left'], ['C2', 'couple', 'C', 'right'],
            ['D1', 'couple', 'D', 'left'], ['D2', 'couple', 'D', 'right'],
        ];
        if ($includeIncidentSeat) {
            $definitions[] = ['E1', 'normal', null, null];
        }
        $seats = collect();
        foreach ($definitions as [$code, $type, $pair, $position]) {
            $seat = Seat::query()->create([
                'room_id' => $room->id, 'row' => $code[0], 'number' => (int) substr($code, 1), 'seat_code' => $code,
                'type' => $type, 'pair_code' => $pair, 'pair_position' => $position, 'status' => 'active',
            ]);
            RoomLayoutCell::query()->create([
                'room_layout_id' => $layout->id,
                'x_position' => (int) substr($code, 1),
                'y_position' => ord($code[0]) - ord('A') + 1,
                'cell_type' => 'seat', 'seat_id' => $seat->id,
            ]);
            $seats->put($code, $seat);
        }
        $seats->each(fn (Seat $seat) => $this->assignLogicalSeatType($seat));
        $layout->update(['status' => 'published', 'published_at' => now()]);
        $this->ensurePublishedPriceBook(50_000);
        $movie = Movie::query()->create(['title' => 'Relocation Movie', 'slug' => 'relocation-'.str()->random(8), 'duration' => 100, 'status' => 'now_showing']);
        $showtime = Showtime::query()->create(['movie_id' => $movie->id, 'cinema_id' => $cinema->id, 'room_id' => $room->id, 'room_layout_id' => $layout->id, 'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id, 'show_date' => now()->addDays(3)->toDateString(), 'show_time' => '19:00:00', 'status' => 'active']);
        $this->snapshotShowtime($showtime);
        $booking = $this->booking($showtime, 'RELOCATE', $paymentStatus === 'success' ? 'paid' : 'pending_payment', $paymentStatus === 'success' ? 'paid' : 'unpaid');
        $original = $seats[$originalCode];
        $isCouple = $original->type === 'couple';
        $bookingSeat = $this->bookingSeat($booking, $original, $isCouple ? 50000 : ($original->type === 'vip' ? 70000 : 50000), $isCouple ? 'couple:C' : 'seat:'.$original->id, $isCouple ? 100000 : ($original->type === 'vip' ? 70000 : 50000));
        $payment = Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id, 'payment_method' => 'vnpay', 'amount' => (int) $booking->total_amount,
            'status' => $paymentStatus, 'verified_at' => $paymentStatus === 'success' ? now() : null, 'paid_at' => $paymentStatus === 'success' ? now() : null,
        ]);
        $manager = $this->userWithRole('manager');

        return compact('cinema', 'room', 'layout', 'seats', 'showtime', 'booking', 'bookingSeat', 'payment', 'manager');
    }

    private function incident(array $scenario): SeatIncident
    {
        $this->actingAs($scenario['manager'])->patch(route('admin.rooms.seat-maintenance.update', [$scenario['room'], $scenario['bookingSeat']->seat_id]), [
            'status' => 'maintenance', 'reason' => 'seat_broken',
        ])->assertRedirect();

        return SeatIncident::query()->with('impacts')->latest('id')->firstOrFail();
    }

    private function booking(Showtime $showtime, string $suffix, string $bookingStatus, string $paymentStatus): Booking
    {
        return Booking::query()->create([
            'showtime_id' => $showtime->id, 'booking_code' => 'MMT-2026-'.str()->upper(str()->random(16)),
            'customer_name' => 'Relocation Customer', 'customer_email' => strtolower($suffix).'@example.test',
            'seat_subtotal' => 50000, 'food_subtotal' => 0, 'gross_amount' => 50000,
            'promotion_discount_amount' => 0, 'total_amount' => 50000,
            'booking_status' => $bookingStatus, 'payment_status' => $paymentStatus, 'expires_at' => now()->addMinutes(15),
        ]);
    }

    private function bookingSeat(Booking $booking, Seat $seat, int $price, ?string $unitKey = null, ?int $unitAmount = null): BookingSeat
    {
        return BookingSeat::query()->create([
            'booking_id' => $booking->id, 'showtime_id' => $booking->showtime_id, 'seat_id' => $seat->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY, 'price' => $price,
            'pricing_unit_key' => $unitKey ?: 'seat:'.$seat->id, 'pricing_unit_label' => $seat->seat_code,
            'seat_type_snapshot' => $seat->type, 'base_amount' => 50000,
            'surcharge_total' => max(0, ($unitAmount ?? $price) - 50000), 'final_unit_amount' => $unitAmount ?? $price,
        ]);
    }

    private function markPrinted($ticket): void
    {
        $ticket->forceFill(['print_count' => 1, 'last_printed_at' => now()])->save();
        BookingTicketPrint::query()->create([
            'admission_ticket_id' => $ticket->id, 'booking_id' => $ticket->booking_id,
            'status' => BookingTicketPrint::STATUS_PRINTED, 'attempts_count' => 1,
            'printed_at' => now(),
        ]);
    }

    private function commercialSnapshots(Booking $booking, BookingSeat $bookingSeat, Payment $payment): array
    {
        $booking = $booking->fresh();
        $bookingSeat = $bookingSeat->fresh();
        $payment = $payment->fresh();

        return [
            $this->raw($booking, ['seat_subtotal', 'food_subtotal', 'gross_amount', 'promotion_discount_amount', 'total_amount', 'booking_status', 'payment_status']),
            $this->seatCommercial($bookingSeat),
            $this->raw($payment, ['status', 'amount', 'currency', 'provider_transaction_id', 'verified_at', 'settled_at']),
        ];
    }

    private function assertCommercialSnapshots(array $snapshots, Booking $booking, BookingSeat $bookingSeat, Payment $payment): void
    {
        $this->assertSame($snapshots[0], $this->raw($booking->fresh(), ['seat_subtotal', 'food_subtotal', 'gross_amount', 'promotion_discount_amount', 'total_amount', 'booking_status', 'payment_status']));
        $this->assertSame($snapshots[1], $this->seatCommercial($bookingSeat->fresh()));
        $this->assertSame($snapshots[2], $this->raw($payment->fresh(), ['status', 'amount', 'currency', 'provider_transaction_id', 'verified_at', 'settled_at']));
    }

    private function seatCommercial(BookingSeat $bookingSeat): array
    {
        return $this->raw($bookingSeat, [
            'price', 'pricing_unit_key', 'pricing_unit_label', 'seat_type_snapshot', 'base_amount',
            'surcharge_total', 'final_unit_amount', 'pricing_breakdown', 'pricing_fingerprint', 'active_lock_key',
        ]);
    }

    private function raw($model, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $model->getRawOriginal($key)])->all();
    }
}
