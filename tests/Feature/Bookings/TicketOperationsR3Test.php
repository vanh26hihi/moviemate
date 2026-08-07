<?php

namespace Tests\Feature\Bookings;

use App\Models\BookingTicketPrint;
use App\Models\BookingTicketPrintEvent;
use App\Models\UserCinemaAssignment;
use App\Services\Tickets\TicketQrPayload;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LogicException;
use Tests\Feature\Payments\PaymentTestCase;

final class TicketOperationsR3Test extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_scanner_has_camera_and_manual_fallback_and_denies_customers(): void
    {
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->get(route('staff.tickets.index'))
            ->assertOk()->assertSee('data-ticket-scanner', false)
            ->assertSee('data-scanner-video', false)->assertSee('data-scanner-input', false)
            ->assertSee('Camera cần HTTPS hoặc localhost');

        $this->actingAs($this->userWithRole('user'))->get(route('staff.tickets.index'))->assertForbidden();
        $javascript = File::get(resource_path('js/ticket-scanner.js'));
        $this->assertStringContainsString("from '@zxing/browser'", $javascript);
        $this->assertStringNotContainsString('https://', $javascript);
    }

    public function test_resolve_is_read_only_and_displays_authoritative_booking_code(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $capability = app(TicketQrPayload::class)->url($booking);

        $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => $capability])
            ->assertOk()->assertSee($booking->booking_code)->assertSee('Vé hợp lệ và đã thanh toán.')
            ->assertSee('Chưa có dữ liệu in')->assertSee('Chưa soát vé');

        $booking->refresh();
        $this->assertSame('paid', $booking->booking_status);
        $this->assertNull($booking->used_at);
        $this->assertDatabaseCount('ticket_checkin_events', 0);
        $this->assertDatabaseCount('booking_ticket_prints', 0);
        $this->assertDatabaseCount('booking_ticket_print_events', 0);
    }

    public function test_tampered_and_cross_branch_capabilities_are_safely_hidden(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $capability = app(TicketQrPayload::class)->url($booking);
        $tampered = substr($capability, 0, -1).($capability[-1] === 'A' ? 'B' : 'A');

        $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => $tampered])->assertNotFound();
        UserCinemaAssignment::query()->where('user_id', $staff->id)->update(['status' => 'revoked']);
        $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => $capability])->assertNotFound();
        $this->actingAs($staff)->get(route('staff.tickets.operations', $booking))->assertNotFound();
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking))->assertNotFound();
        $this->assertDatabaseCount('booking_ticket_prints', 0);
    }

    public function test_resolve_rate_limit_does_not_create_operational_history(): void
    {
        $staff = $this->userWithRole('staff');
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => 'malformed'])->assertNotFound();
        }
        $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => 'malformed'])->assertTooManyRequests();
        $this->assertDatabaseCount('booking_ticket_print_events', 0);
        $this->assertDatabaseCount('ticket_checkin_events', 0);
    }

    public function test_initial_print_is_idempotent_and_success_does_not_check_in(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('staff.tickets.operations', $booking))->assertOk();
        $this->assertDatabaseCount('booking_ticket_prints', 0);

        $this->post(route('staff.tickets.print.start', $booking))->assertRedirect(route('staff.tickets.print.show', $booking));
        $this->post(route('staff.tickets.print.start', $booking))->assertRedirect(route('staff.tickets.print.show', $booking));
        $state = BookingTicketPrint::query()->sole();
        $this->assertSame(1, $state->attempts_count);
        $this->assertSame(1, BookingTicketPrintEvent::query()->where('event_type', 'print_started')->count());

        $this->get(route('staff.tickets.print.show', $booking))
            ->assertOk()->assertSee($booking->booking_code)->assertSee('data-staff-print-trigger', false)
            ->assertDontSee('provider')->assertDontSee('ticket_email_token');
        $this->get(route('staff.tickets.print.show', $booking))->assertOk();
        $this->assertSame(1, BookingTicketPrint::query()->sole()->attempts_count);

        $this->post(route('staff.tickets.print.succeed', $booking))
            ->assertRedirect(route('staff.tickets.operations', $booking));
        $state->refresh();
        $this->assertSame(BookingTicketPrint::STATUS_PRINTED, $state->status);
        $this->assertSame($staff->id, $state->printed_by_user_id);
        $this->assertNotNull($state->printed_at);
        $this->assertSame(1, BookingTicketPrintEvent::query()->where('event_type', 'print_succeeded')->count());
        $this->post(route('staff.tickets.print.succeed', $booking))->assertRedirect(route('staff.tickets.operations', $booking));
        $this->assertSame(1, BookingTicketPrintEvent::query()->where('event_type', 'print_succeeded')->count());
        $this->assertSame('paid', $booking->fresh()->booking_status);
        $this->assertNull($booking->fresh()->used_at);
        $this->assertDatabaseCount('ticket_checkin_events', 0);
        $this->post(route('staff.tickets.print.start', $booking))->assertStatus(409);
    }

    public function test_documented_failure_allows_one_retry_then_requires_manager_authorization(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking));
        $this->post(route('staff.tickets.print.fail', $booking), ['failure_code' => 'paper_jam'])
            ->assertRedirect(route('staff.tickets.operations', $booking));
        $this->assertSame(BookingTicketPrint::STATUS_RETRY_ALLOWED, BookingTicketPrint::query()->sole()->status);

        $this->post(route('staff.tickets.print.start', $booking));
        $this->assertSame(2, BookingTicketPrint::query()->sole()->attempts_count);
        $this->post(route('staff.tickets.print.fail', $booking), ['failure_code' => 'printer_offline']);
        $state = BookingTicketPrint::query()->sole();
        $this->assertSame(BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION, $state->status);
        $this->post(route('staff.tickets.print.start', $booking))->assertStatus(409);

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->post(route('admin.bookings.ticket-print.authorize-retry', $booking), ['safe_note' => 'Đã kiểm tra máy in dự phòng.'])
            ->assertRedirect()->assertSessionHas('success', 'Đã cho phép thực hiện thêm một lần in.');
        $state->refresh();
        $this->assertSame(BookingTicketPrint::STATUS_RETRY_AUTHORIZED, $state->status);
        $this->assertSame($manager->id, $state->retry_authorized_by_user_id);
        $this->assertNull($state->printed_at);
        $this->assertDatabaseHas('booking_ticket_print_events', ['event_type' => 'retry_authorized']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ticket.print_retry_authorized']);
    }

    public function test_failure_validation_and_unauthorized_override_are_enforced(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking));
        $this->post(route('staff.tickets.print.fail', $booking), [])->assertSessionHasErrors('failure_code');
        $this->post(route('staff.tickets.print.fail', $booking), ['failure_code' => 'other'])->assertSessionHasErrors('safe_note');
        $this->post(route('staff.tickets.print.fail', $booking), ['failure_code' => 'raw_exception'])->assertSessionHasErrors('failure_code');
        $this->assertSame(BookingTicketPrint::STATUS_PRINTING, BookingTicketPrint::query()->sole()->status);
        $this->post(route('staff.tickets.print.fail', $booking), ['failure_code' => 'other', 'safe_note' => '<b>Máy không nhận giấy</b>']);
        $this->assertDatabaseHas('booking_ticket_print_events', ['failure_code' => 'other', 'safe_note' => 'Máy không nhận giấy']);
        $this->actingAs($staff)->post(route('admin.bookings.ticket-print.authorize-retry', $booking))->assertForbidden();
    }

    public function test_expired_print_operation_is_released_and_requires_authorization(): void
    {
        $booking = $this->verifiedBooking();
        $firstStaff = $this->userWithRole('staff');
        $this->actingAs($firstStaff)->post(route('staff.tickets.print.start', $booking))->assertRedirect();
        $this->travel(11)->minutes();

        $secondStaff = $this->userWithRole('staff');
        $this->actingAs($secondStaff)->post(route('staff.tickets.print.start', $booking))->assertStatus(409);

        $state = BookingTicketPrint::query()->sole();
        $this->assertSame(BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION, $state->status);
        $this->assertNull($state->active_operation_id);
        $this->assertDatabaseHas('booking_ticket_print_events', ['event_type' => 'stale_print_released']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ticket.print_stale_released']);
    }

    public function test_unpaid_cancelled_refunded_expired_and_used_bookings_cannot_start_print(): void
    {
        $staff = $this->userWithRole('staff');
        $bookings = [
            $this->payableBooking(),
            $this->verifiedBooking(['booking_status' => 'cancelled']),
            $this->verifiedBooking(['payment_status' => 'refunded']),
            $this->verifiedBooking(['booking_status' => 'expired']),
            $this->verifiedBooking(['booking_status' => 'used', 'used_at' => now()]),
        ];
        foreach ($bookings as $booking) {
            $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking))->assertStatus(409);
        }
        $this->assertDatabaseCount('booking_ticket_prints', 0);
    }

    public function test_print_events_are_append_only_and_contain_no_capability_columns(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking));
        $event = BookingTicketPrintEvent::query()->sole();
        foreach (['token', 'capability', 'qr_payload', 'printer_ip'] as $column) {
            $this->assertNotContains($column, DB::getSchemaBuilder()->getColumnListing('booking_ticket_print_events'));
        }
        try {
            $event->forceFill(['event_type' => 'changed'])->save();
            $this->fail('Model update must fail.');
        } catch (LogicException) {
            $this->assertSame('print_started', $event->fresh()->event_type);
        }
        $this->expectException(QueryException::class);
        DB::table('booking_ticket_print_events')->where('id', $event->id)->delete();
    }

    public function test_r3_ticket_surfaces_have_bounded_query_counts(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $payment = $this->pendingPayment($booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);
        $booking = $booking->fresh();
        $staff = $this->userWithRole('staff');
        $manager = $this->userWithRole('manager');
        $capability = app(TicketQrPayload::class)->url($booking);

        $counts = [
            'customer_ticket' => $this->queryCount(fn () => $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))->assertOk()),
            'scanner_index' => $this->queryCount(fn () => $this->actingAs($staff)->get(route('staff.tickets.index'))->assertOk()),
            'scanner_resolve' => $this->queryCount(fn () => $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => $capability])->assertOk()),
            'staff_operations' => $this->queryCount(fn () => $this->actingAs($staff)->get(route('staff.tickets.operations', $booking))->assertOk()),
        ];
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking))->assertRedirect();
        $counts['staff_print_page'] = $this->queryCount(fn () => $this->actingAs($staff)->get(route('staff.tickets.print.show', $booking))->assertOk());
        $counts['admin_index'] = $this->queryCount(fn () => $this->actingAs($manager)->get(route('admin.bookings.index'))->assertOk());
        $counts['admin_detail'] = $this->queryCount(fn () => $this->actingAs($manager)->get(route('admin.bookings.show', $booking))->assertOk());

        foreach ($counts as $surface => $count) {
            $this->assertLessThanOrEqual(30, $count, $surface.' has an unexpected query count.');
        }
    }

    private function queryCount(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function verifiedBooking(array $overrides = [])
    {
        $payment = $this->pendingPayment();
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);
        $booking = $payment->booking->fresh();
        if ($overrides !== []) {
            $booking->forceFill($overrides)->save();
        }

        return $booking->fresh();
    }
}
