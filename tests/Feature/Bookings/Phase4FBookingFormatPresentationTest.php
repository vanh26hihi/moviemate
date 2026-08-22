<?php

namespace Tests\Feature\Bookings;

use App\Mail\BookingTicketMail;
use App\Models\Payment;
use App\Models\PresentationFormat;
use App\Services\Tickets\BookingPrintAmountAllocator;
use App\Services\Tickets\BookingQrPayload;
use App\Services\Tickets\TicketArtifactProvisioner;
use App\Services\Tickets\TicketQrCode;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Payments\PaymentTestCase;

final class Phase4FBookingFormatPresentationTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_seat_selection_displays_the_showtime_format_without_changing_booking_state(): void
    {
        $scenario = $this->bookingScenario(false);
        $format = $this->assignFormat($scenario, '3D');
        $before = [
            'bookings' => DB::table('bookings')->count(),
            'booking_seats' => DB::table('booking_seats')->count(),
        ];

        $this->get(route('user.bookings.selectSeat', $scenario['showtime']))
            ->assertOk()
            ->assertSee('Định dạng trình chiếu:')
            ->assertSee($format->name)
            ->assertSee($scenario['room']->name);

        $this->assertSame($before, [
            'bookings' => DB::table('bookings')->count(),
            'booking_seats' => DB::table('booking_seats')->count(),
        ]);
    }

    public function test_booking_pages_keep_the_sold_showtime_format_after_master_relationships_change(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $format = $this->assignFormat($scenario, '3D');
        $booking = $this->paidBooking($scenario, $owner->id);

        $scenario['movie']->supportedPresentationFormats()->detach($format);
        $scenario['room']->presentationCapabilities()->detach($format);
        $format->update(['is_active' => false]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))->assertOk();
        $bookingDetailQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertSee('Định dạng')->assertSee('3D');
        $this->get(route('user.bookings.success', $booking))->assertOk()
            ->assertSee('Định dạng')->assertSee('3D');
        $this->get(route('user.bookings.history'))->assertOk()
            ->assertSee('Định dạng')->assertSee('3D');
        $this->assertLessThanOrEqual(25, $bookingDetailQueries, 'Booking detail query count is unbounded.');

        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PHASE4F_BOOKING_DETAIL_QUERIES='.$bookingDetailQueries.PHP_EOL);
        }
    }

    public function test_booking_confirmation_email_uses_showtime_format_and_not_room_type(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $scenario['room']->update(['room_type' => 'IMAX']);
        $this->assignFormat($scenario, '3D');
        $booking = $this->paidBooking($scenario, $owner->id);
        $payload = app(BookingQrPayload::class)->value($booking);

        $html = (new BookingTicketMail(
            $booking,
            'https://example.test/ticket',
            app(TicketQrCode::class)->png($payload),
        ))->render();

        $this->assertStringContainsString('Định dạng', $html);
        $this->assertMatchesRegularExpression('/Định dạng<\/td>\s*<td[^>]*>3D<\/td>/', $html);
        $this->assertDoesNotMatchRegularExpression('/Định dạng<\/td>\s*<td[^>]*>IMAX<\/td>/', $html);
    }

    public function test_physical_ticket_keeps_format_separate_from_room_for_imax_2d_and_standard_3d(): void
    {
        $staff = $this->userWithRole('staff');

        foreach ([['IMAX', '2D'], ['STANDARD', '3D']] as [$roomType, $formatCode]) {
            $scenario = $this->bookingScenario(false);
            $scenario['room']->update(['room_type' => $roomType]);
            $this->assignFormat($scenario, $formatCode);
            $booking = $this->paidBooking($scenario, $this->userWithRole('user')->id);
            $ticket = $booking->admissionTickets()->sole();

            $this->actingAs($staff)
                ->post(route('staff.admission-tickets.print.start', $ticket))
                ->assertRedirect(route('staff.admission-tickets.print.show', $ticket));
            $response = $this->get(route('staff.admission-tickets.print.show', $ticket))->assertOk();

            $response->assertSee($scenario['room']->name)
                ->assertSee('<dt>Định dạng</dt><dd>'.$formatCode.'</dd>', false)
                ->assertDontSee('<dt>Định dạng</dt><dd>'.$roomType.'</dd>', false);
            $this->post(route('staff.admission-tickets.print.succeed', $ticket))->assertRedirect();
        }
    }

    public function test_reprint_uses_the_same_showtime_format_and_keeps_allocated_amount_stable(): void
    {
        $scenario = $this->bookingScenario(false);
        $scenario['room']->update(['room_type' => 'STANDARD']);
        $format = $this->assignFormat($scenario, '3D');
        $booking = $this->paidBooking($scenario, $this->userWithRole('user')->id);
        $ticket = $booking->admissionTickets()->sole();
        $staff = $this->userWithRole('staff');
        $beforeAmount = app(BookingPrintAmountAllocator::class)->allocate($booking)->forTicket($ticket);

        $this->actingAs($staff)->post(route('staff.admission-tickets.print.start', $ticket))->assertRedirect();
        $first = $this->get(route('staff.admission-tickets.print.show', $ticket))->assertOk();
        $first->assertSee('<dt>Định dạng</dt><dd>3D</dd>', false);
        $this->post(route('staff.admission-tickets.print.succeed', $ticket))->assertRedirect();

        $scenario['movie']->supportedPresentationFormats()->detach($format);
        $scenario['room']->presentationCapabilities()->detach($format);
        $scenario['room']->update(['room_type' => 'IMAX']);
        $format->update(['is_active' => false]);

        $this->post(route('staff.admission-tickets.print.reprint', $ticket), ['reason_code' => 'paper_jam'])
            ->assertRedirect(route('staff.admission-tickets.print.show', $ticket));
        $reprint = $this->get(route('staff.admission-tickets.print.show', $ticket))->assertOk();
        $reprint->assertSee('<dt>Định dạng</dt><dd>3D</dd>', false)
            ->assertDontSee('<dt>Định dạng</dt><dd>IMAX</dd>', false);
        $this->post(route('staff.admission-tickets.print.succeed', $ticket))->assertRedirect();

        $this->assertSame($beforeAmount, app(BookingPrintAmountAllocator::class)->allocate($booking->fresh())->forTicket($ticket));
        $this->assertSame(2, $ticket->fresh()->print_count);
        $this->assertDatabaseHas('booking_ticket_print_events', [
            'admission_ticket_id' => $ticket->id,
            'event_type' => 'reprint_requested',
            'failure_code' => 'paper_jam',
        ]);
    }

    public function test_print_all_loads_format_once_for_all_physical_tickets(): void
    {
        $scenario = $this->bookingScenario(true);
        $this->assignFormat($scenario, '3D');
        $seatIds = $scenario['seats']->where('type', 'couple')->pluck('id')->all();
        $booking = $this->paidBooking($scenario, $this->userWithRole('user')->id, $seatIds);
        $staff = $this->userWithRole('staff');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($staff)->post(route('staff.tickets.print-all', $booking))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $formatQueries = collect($queries)->filter(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'presentation_formats'),
        )->count();

        $this->assertSame(2, substr_count($response->getContent(), '<dt>Định dạng</dt><dd>3D</dd>'));
        $this->assertSame(1, $formatQueries);
        $this->assertLessThanOrEqual(80, count($queries), 'Print All query count is unbounded.');

        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PHASE4F_PRINT_ALL_QUERIES='.count($queries).';FORMAT_QUERIES='.$formatQueries.PHP_EOL);
        }
    }

    private function assignFormat(array $scenario, string $code): PresentationFormat
    {
        $format = PresentationFormat::query()->firstOrCreate(['code' => $code], [
            'name' => $code,
            'is_active' => true,
            'sort_order' => $code === '2D' ? 10 : 20,
        ]);
        $scenario['movie']->supportedPresentationFormats()->syncWithoutDetaching($format);
        $scenario['room']->presentationCapabilities()->syncWithoutDetaching($format);
        $scenario['showtime']->update(['presentation_format_id' => $format->id]);

        return $format;
    }

    private function paidBooking(array $scenario, int $ownerId, ?array $seatIds = null)
    {
        $booking = $this->reserve($scenario, $seatIds ?? [$scenario['seats'][0]->id], $ownerId)->booking;
        $payment = $this->pendingPayment($booking, ['amount' => (int) $booking->total_amount]);
        $payment->forceFill([
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
        ])->save();
        $booking->forceFill([
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
            'expires_at' => null,
        ])->save();
        app(TicketArtifactProvisioner::class)->provision($booking->fresh());

        return $booking->fresh();
    }
}
