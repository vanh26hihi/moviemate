<?php

namespace Tests\Feature\Bookings;

use App\Models\BookingTicketPrint;
use App\Models\BookingTicketPrintEvent;
use App\Models\Cinema;
use App\Models\UserCinemaAssignment;
use App\Services\Tickets\BookingQrPayload;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
        $capability = app(BookingQrPayload::class)->value($booking);

        $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => $capability])
            ->assertOk()->assertSee($booking->booking_code)->assertSee('Vé xem phim theo ghế')
            ->assertSee('Vé vật lý');
        $this->post(route('staff.tickets.resolve'), ['ticket' => strtolower($booking->booking_code)])
            ->assertOk()->assertSee($booking->booking_code)->assertSee('Vé xem phim theo ghế');

        $booking->refresh();
        $this->assertSame('paid', $booking->booking_status);
        $this->assertDatabaseCount('booking_ticket_prints', 0);
        $this->assertDatabaseCount('booking_ticket_print_events', 0);
    }

    public function test_tampered_and_cross_branch_capabilities_are_safely_hidden(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $capability = app(BookingQrPayload::class)->value($booking);
        $tampered = substr($capability, 0, -1).($capability[-1] === 'A' ? 'B' : 'A');

        $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => $tampered])->assertNotFound();
        UserCinemaAssignment::query()->where('user_id', $staff->id)->update(['status' => 'revoked']);
        $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => $capability])->assertNotFound();
        $this->actingAs($staff)->get(route('staff.tickets.operations', $booking))->assertNotFound();
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking))->assertNotFound();
        $this->assertDatabaseCount('booking_ticket_prints', 0);
    }

    public function test_revoked_branch_assignment_invalidates_every_active_print_action(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking))->assertRedirect();
        UserCinemaAssignment::query()->where('user_id', $staff->id)->update(['status' => 'revoked']);

        $this->get(route('staff.tickets.print.show', $booking))->assertNotFound();
        $this->post(route('staff.tickets.print.reprint', $booking), ['reason_code' => 'printer_error'])->assertNotFound();
        $this->post(route('staff.tickets.print.succeed', $booking))->assertNotFound();
        $this->post(route('staff.tickets.print.fail', $booking), ['failure_code' => 'paper_jam'])->assertNotFound();
        $this->travel(11)->minutes();
        $this->post(route('staff.tickets.print.recover-expired', $booking))->assertNotFound();

        $state = BookingTicketPrint::query()->sole();
        $this->assertSame(BookingTicketPrint::STATUS_PRINTING, $state->status);
        $this->assertSame(1, $state->attempts_count);
        $this->assertDatabaseCount('booking_ticket_print_events', 1);
    }

    public function test_resolve_rate_limit_does_not_create_operational_history(): void
    {
        $staff = $this->userWithRole('staff');
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => 'malformed'])->assertNotFound();
        }
        $this->actingAs($staff)->post(route('staff.tickets.resolve'), ['ticket' => 'malformed'])->assertTooManyRequests();
        $this->assertDatabaseCount('booking_ticket_print_events', 0);
    }

    public function test_initial_print_is_idempotent_and_does_not_change_booking_state(): void
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
            ->assertSee('MOVIEMATE')->assertSee('VÉ VÀO PHÒNG CHIẾU PHIM')->assertSee('width:80mm', false)
            ->assertSee($booking->admissionTickets()->sole()->ticket_code)
            ->assertDontSee('data-qr-value', false)
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
        $this->post(route('staff.tickets.print.start', $booking))->assertStatus(409);
    }

    public function test_multiple_reason_backed_reprints_after_success_need_no_approval(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking));
        $this->post(route('staff.tickets.print.succeed', $booking));
        $this->post(route('staff.tickets.print.start', $booking))->assertStatus(409);

        foreach ([
            ['reason_code' => 'damaged_ticket'],
            ['reason_code' => 'customer_lost_ticket'],
            ['reason_code' => 'other', 'safe_note' => '<b>Khách cần bản thay thế tại quầy</b>'],
        ] as $index => $payload) {
            $this->post(route('staff.tickets.print.reprint', $booking), $payload)
                ->assertRedirect(route('staff.tickets.print.show', $booking));
            $this->assertSame($index + 2, BookingTicketPrint::query()->sole()->attempts_count);
            $this->post(route('staff.tickets.print.succeed', $booking))
                ->assertRedirect(route('staff.tickets.operations', $booking));
        }

        $state = BookingTicketPrint::query()->sole();
        $this->assertSame(BookingTicketPrint::STATUS_PRINTED, $state->status);
        $this->assertSame(4, $state->attempts_count);
        $this->assertSame($staff->id, $state->printed_by_user_id);
        $this->assertSame(3, BookingTicketPrintEvent::query()->where('event_type', 'reprint_requested')->count());
        $this->assertSame(4, BookingTicketPrintEvent::query()->where('event_type', 'print_started')->count());
        $this->assertSame(4, BookingTicketPrintEvent::query()->where('event_type', 'print_succeeded')->count());
        $this->assertDatabaseHas('booking_ticket_print_events', [
            'event_type' => 'reprint_requested',
            'attempt_number' => 4,
            'failure_code' => 'other',
            'safe_note' => 'Khách cần bản thay thế tại quầy',
            'actor_user_id' => $staff->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ticket.reprint_requested']);
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('admin.bookings.ticket-print.authorize-retry'));
    }

    public function test_reprint_reason_and_failure_validation_are_enforced(): void
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

        $beforeEvents = BookingTicketPrintEvent::query()->count();
        $this->post(route('staff.tickets.print.reprint', $booking), [])->assertSessionHasErrors('reason_code');
        $this->post(route('staff.tickets.print.reprint', $booking), ['reason_code' => 'other'])->assertSessionHasErrors('safe_note');
        $this->post(route('staff.tickets.print.reprint', $booking), [
            'reason_code' => 'other',
            'safe_note' => str_repeat('x', 301),
        ])->assertSessionHasErrors('safe_note');
        $this->post(route('staff.tickets.print.reprint', $booking), ['reason_code' => 'unbounded_raw'])->assertSessionHasErrors('reason_code');
        $state = BookingTicketPrint::query()->sole();
        $this->assertSame(1, $state->attempts_count);
        $this->assertNull($state->active_operation_id);
        $this->assertSame($beforeEvents, BookingTicketPrintEvent::query()->count());
    }

    public function test_repeated_failures_can_each_be_reprinted_with_an_explicit_reason(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking));
        $this->post(route('staff.tickets.print.fail', $booking), ['failure_code' => 'printer_error']);

        foreach (['printer_error', 'paper_jam'] as $reason) {
            $this->post(route('staff.tickets.print.reprint', $booking), ['reason_code' => $reason])->assertRedirect();
            $this->post(route('staff.tickets.print.fail', $booking), ['failure_code' => $reason])->assertRedirect();
            $this->assertSame(BookingTicketPrint::STATUS_RETRY_ALLOWED, BookingTicketPrint::query()->sole()->status);
        }

        $this->post(route('staff.tickets.print.reprint', $booking), ['reason_code' => 'out_of_paper'])->assertRedirect();
        $this->post(route('staff.tickets.print.succeed', $booking))->assertRedirect();
        $this->assertSame(4, BookingTicketPrint::query()->sole()->attempts_count);
        $this->assertSame(3, BookingTicketPrintEvent::query()->where('event_type', 'reprint_requested')->count());
        $this->assertDatabaseMissing('booking_ticket_print_events', ['event_type' => 'retry_authorized']);
    }

    public function test_cross_branch_reprint_is_hidden_even_with_a_valid_booking_id_and_reason(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $otherCinema = Cinema::factory()->create(['is_primary' => false, 'status' => 'active']);
        $booking->forceFill(['cinema_id' => $otherCinema->id])->save();
        BookingTicketPrint::query()->create([
            'admission_ticket_id' => $booking->admissionTickets()->sole()->id,
            'booking_id' => $booking->id,
            'status' => BookingTicketPrint::STATUS_PRINTED,
            'attempts_count' => 1,
            'printed_by_user_id' => $staff->id,
            'printed_at' => now(),
        ]);

        $this->actingAs($staff)->get(route('staff.tickets.operations', $booking))->assertNotFound();
        $this->post(route('staff.tickets.print.reprint', $booking), ['reason_code' => 'damaged_ticket'])->assertNotFound();
        $this->assertSame(1, BookingTicketPrint::query()->sole()->attempts_count);
        $this->assertDatabaseCount('booking_ticket_print_events', 0);
    }

    public function test_expired_print_back_navigation_recovers_without_get_mutation(): void
    {
        $booking = $this->verifiedBooking();
        $firstStaff = $this->userWithRole('staff');
        $this->actingAs($firstStaff)->post(route('staff.tickets.print.start', $booking))->assertRedirect();
        $this->travel(11)->minutes();

        $this->get(route('staff.tickets.print.show', $booking))
            ->assertRedirect(route('staff.tickets.operations', $booking))
            ->assertSessionHas('warning', 'Phiên in trước đã hết hiệu lực. Vui lòng xác nhận kết quả lần in trước trước khi tiếp tục.');

        $state = BookingTicketPrint::query()->sole();
        $this->assertSame(BookingTicketPrint::STATUS_PRINTING, $state->status);
        $this->assertSame(1, $state->attempts_count);
        $this->assertDatabaseCount('booking_ticket_print_events', 1);

        $this->post(route('staff.tickets.print.recover-expired', $booking))
            ->assertRedirect(route('staff.tickets.operations', $booking));
        $state->refresh();
        $this->assertSame(BookingTicketPrint::STATUS_RETRY_ALLOWED, $state->status);
        $this->assertNull($state->active_operation_id);
        $this->assertDatabaseHas('booking_ticket_print_events', [
            'event_type' => 'print_failed',
            'failure_code' => 'browser_interrupted',
        ]);
    }

    public function test_back_to_consumed_print_redirects_to_printed_status_without_new_attempt(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking));
        $this->post(route('staff.tickets.print.succeed', $booking));

        $this->get(route('staff.tickets.print.show', $booking))
            ->assertRedirect(route('staff.tickets.operations', $booking))
            ->assertSessionHas('success', 'Vé này đã được ghi nhận in thành công.');
        $this->assertSame(1, BookingTicketPrint::query()->sole()->attempts_count);
        $this->assertSame(1, BookingTicketPrintEvent::query()->where('event_type', 'print_succeeded')->count());
    }

    public function test_staff_and_admin_details_report_reprints_without_an_approval_action(): void
    {
        $booking = $this->verifiedBooking();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking));
        $this->post(route('staff.tickets.print.succeed', $booking));
        $this->post(route('staff.tickets.print.reprint', $booking), ['reason_code' => 'faded_ticket']);
        $this->post(route('staff.tickets.print.succeed', $booking));

        $this->get(route('staff.tickets.operations', $booking))->assertOk()
            ->assertSee('Số bản đã in')->assertSee('Vé bị nhòe mực')
            ->assertSee('In lại vé')->assertSee('Lịch sử in')
            ->assertDontSee('phê duyệt', false);

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee('Số lần in')->assertSee('Số lần in lại')
            ->assertSee('Người in gần nhất')->assertSee('Lý do in lại gần nhất')
            ->assertSee('Vé bị nhòe mực')->assertSee('Yêu cầu in lại')
            ->assertDontSee('Cho phép thêm một lần in')
            ->assertDontSee('Phê duyệt in lại');
    }

    public function test_ineligible_bookings_cannot_print(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $staff = $this->userWithRole('staff');
        $bookings = [
            $this->payableBooking(),
            $this->verifiedBooking(['booking_status' => 'cancelled']),
            $this->verifiedBooking(['payment_status' => 'refunded']),
            $this->verifiedBooking(['booking_status' => 'expired']),
        ];
        foreach ($bookings as $booking) {
            $this->actingAs($staff)->post(route('staff.tickets.print.start', $booking))->assertStatus(409);
            $this->post(route('staff.tickets.print-all', $booking))->assertStatus(409);
            $this->get(route('staff.tickets.operations', $booking))->assertOk()
                ->assertSee('Đơn chỉ được xem, không được in')
                ->assertDontSee('In toàn bộ')
                ->assertDontSee('>In vé<', false)
                ->assertDontSee('In phiếu nhận đồ ăn');
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
        $capability = app(BookingQrPayload::class)->value($booking);

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
