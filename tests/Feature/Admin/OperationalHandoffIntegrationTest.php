<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SeatIncident;
use App\Models\SeatIncidentImpact;
use App\Models\SeatIncidentResolution;
use App\Models\SeatIncidentSeat;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Payments\PaymentTestCase;

final class OperationalHandoffIntegrationTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_room_detail_links_bounded_showtimes_and_authoritative_maintenance_incidents(): void
    {
        $scenario = $this->bookingScenario(false);
        $room = $scenario['room'];
        $showtime = $scenario['showtime'];
        $incident = SeatIncident::query()->create([
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'status' => SeatIncident::STATUS_OPEN,
            'reason' => SeatIncident::REASON_BROKEN,
        ]);
        $manager = $this->userWithRole('manager');
        $session = [CinemaAccessService::SESSION_KEY => $scenario['cinema']->id];

        $response = $this->actingAs($manager)->withSession($session)
            ->get(route('admin.rooms.show', $room))->assertOk()
            ->assertSee('Lịch chiếu của phòng')
            ->assertSee($scenario['movie']->title)
            ->assertSee($showtime->presentationFormat->name)
            ->assertSee(route('admin.showtimes.show', $showtime), false)
            ->assertSee(route('admin.rooms.seat-maintenance.index', $room), false)
            ->assertSee(route('admin.rooms.seat-incidents.show', [$room, $incident]), false);

        $this->assertLessThanOrEqual(8, $response->viewData('upcomingShowtimes')->count());
        $this->assertSame(1, $response->viewData('openIncidentsCount'));

        $foreignCinema = Cinema::factory()->create(['is_primary' => false, 'status' => 'active']);
        $foreignRoom = Room::factory()->create(['cinema_id' => $foreignCinema->id]);
        $this->actingAs($manager)->withSession($session)
            ->get(route('admin.rooms.show', $foreignRoom))->assertNotFound();
        $this->get(route('admin.showtimes.show', $this->foreignShowtime($foreignRoom)))
            ->assertNotFound();
    }

    public function test_room_without_incident_does_not_invent_alert_or_count_blocked_layout_cells(): void
    {
        $scenario = $this->bookingScenario(false, [[
            'x_position' => 4,
            'y_position' => 2,
            'cell_type' => 'blocked',
        ]]);

        $response = $this->actingAs($this->userWithRole('manager'))
            ->withSession([CinemaAccessService::SESSION_KEY => $scenario['cinema']->id])
            ->get(route('admin.rooms.show', $scenario['room']))->assertOk()
            ->assertSee('Không có sự cố ghế đang mở')
            ->assertDontSee('data-open-seat-incident', false);

        $this->assertSame(0, $response->viewData('openIncidentsCount'));
    }

    public function test_booking_links_every_payment_attempt_and_counter_without_print_side_effects(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->paidBooking($scenario);
        $verified = $this->onlinePayment($booking);
        $pending = $this->pendingPayment($booking, ['amount' => (int) $booking->total_amount]);
        $beforePrints = $booking->admissionTickets()->sum('print_count');

        $this->actingAs($this->userWithRole('manager'))
            ->withSession([CinemaAccessService::SESSION_KEY => $scenario['cinema']->id])
            ->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee(route('admin.payments.show', $verified), false)
            ->assertSee(route('admin.payments.show', $pending), false)
            ->assertSee('Tra cứu & in tại quầy', false)
            ->assertSee(route('staff.tickets.operations', $booking), false);

        $this->assertSame($beforePrints, $booking->admissionTickets()->sum('print_count'));
        $this->assertDatabaseCount('booking_ticket_print_events', 0);

        $foreign = $this->foreignBooking();
        $foreignPayment = $this->onlinePayment($foreign);
        $manager = $this->userWithRole('manager');
        $session = [CinemaAccessService::SESSION_KEY => $scenario['cinema']->id];
        $this->actingAs($manager)->withSession($session)
            ->get(route('admin.bookings.show', $foreign))->assertNotFound();
        $this->get(route('admin.payments.show', $foreignPayment))->assertNotFound();
    }

    public function test_internal_zero_payment_remains_truthful_without_external_provider_evidence(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->paidBooking($scenario);
        $payment = Payment::query()->forceCreate([
            'booking_id' => $booking->id,
            'provider' => Payment::PROVIDER_INTERNAL_ZERO,
            'payment_method' => Payment::PROVIDER_INTERNAL_ZERO,
            'amount' => 0,
            'currency' => 'VND',
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
        ]);

        $this->actingAs($this->userWithRole('manager'))
            ->withSession([CinemaAccessService::SESSION_KEY => $scenario['cinema']->id])
            ->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee(route('admin.payments.show', $payment), false)
            ->assertSee('Khuyến mãi toàn phần')
            ->assertDontSee('Thiếu giao dịch nhà cung cấp');
    }

    public function test_booking_incident_context_uses_exact_impact_and_preserves_relocation_and_refund_semantics(): void
    {
        $scenario = $this->bookingScenario();
        $booking = $this->paidBooking($scenario, 3);
        $bookingSeats = $booking->bookingSeats()->with('seat')->orderBy('id')->get();
        $replacement = $scenario['seats']->firstWhere('seat_code', 'A2');
        $replacement->update(['status' => 'active']);
        $incident = SeatIncident::query()->create([
            'cinema_id' => $scenario['cinema']->id,
            'room_id' => $scenario['room']->id,
            'status' => SeatIncident::STATUS_OPEN,
            'reason' => SeatIncident::REASON_SAFETY,
        ]);
        foreach ($bookingSeats as $bookingSeat) {
            SeatIncidentSeat::query()->create([
                'seat_incident_id' => $incident->id,
                'seat_id' => $bookingSeat->seat_id,
                'active_lock_key' => 'ACTIVE',
            ]);
        }
        $relocated = $this->incidentImpact($incident, $bookingSeats[0]);
        SeatIncidentResolution::query()->create([
            'seat_incident_impact_id' => $relocated->id,
            'operation_id' => fake()->uuid(),
            'resolution_type' => SeatIncidentResolution::TYPE_EQUIVALENT,
            'original_seat_id' => $bookingSeats[0]->seat_id,
            'replacement_seat_id' => $replacement->id,
            'original_pre_promotion_amount' => 50_000,
            'replacement_hypothetical_amount' => 50_000,
            'reprint_required' => true,
        ]);
        $refund = $this->incidentImpact($incident, $bookingSeats[1]);
        SeatIncidentResolution::query()->create([
            'seat_incident_impact_id' => $refund->id,
            'operation_id' => fake()->uuid(),
            'resolution_type' => SeatIncidentResolution::TYPE_REQUIRES_REFUND,
            'original_seat_id' => $bookingSeats[1]->seat_id,
            'replacement_seat_id' => null,
            'original_pre_promotion_amount' => 50_000,
            'replacement_hypothetical_amount' => null,
            'reprint_required' => false,
        ]);

        $this->actingAs($this->userWithRole('manager'))
            ->withSession([CinemaAccessService::SESSION_KEY => $scenario['cinema']->id])
            ->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee('Sự cố ghế liên quan')
            ->assertSee(route('admin.rooms.seat-incidents.show', [$scenario['room'], $incident]), false)
            ->assertSee($bookingSeats[0]->seat->seat_code.' → A2')
            ->assertSee('Không phát sinh thu thêm')
            ->assertSee('Chức năng hoàn tiền chưa được triển khai');

        $unaffected = $this->bookingForScenario($scenario);
        $this->actingAs($this->userWithRole('manager'))
            ->withSession([CinemaAccessService::SESSION_KEY => $scenario['cinema']->id])
            ->get(route('admin.bookings.show', $unaffected))->assertOk()
            ->assertDontSee('data-booking-incident-context', false);
    }

    public function test_staff_operations_show_authoritative_payment_format_and_room_type_separately(): void
    {
        $scenario = $this->bookingScenario(false);
        $roomType = RoomType::query()->create([
            'code' => 'IMAX_TEST',
            'name' => 'IMAX Human Label',
            'is_active' => true,
            'sort_order' => 90,
        ]);
        $format = PresentationFormat::query()->create([
            'code' => 'THREE_D_TEST',
            'name' => '3D Operational',
            'is_active' => true,
            'sort_order' => 90,
        ]);
        $scenario['room']->forceFill([
            'room_type' => $roomType->code,
            'room_type_id' => $roomType->id,
        ])->save();
        $scenario['movie']->supportedPresentationFormats()->syncWithoutDetaching($format);
        $scenario['room']->presentationCapabilities()->syncWithoutDetaching($format);
        $scenario['showtime']->update(['presentation_format_id' => $format->id]);
        $booking = $this->paidBooking($scenario);
        $payment = $this->onlinePayment($booking);

        $this->actingAs($this->userWithRole('staff'))
            ->withSession([CinemaAccessService::SESSION_KEY => $scenario['cinema']->id])
            ->get(route('staff.tickets.operations', $booking))->assertOk()
            ->assertSee('Bằng chứng thanh toán')
            ->assertSee('Đã xác minh lúc')
            ->assertSee($payment->verified_at->format('d/m/Y H:i:s'))
            ->assertSee('Định dạng trình chiếu')
            ->assertSee('3D Operational')
            ->assertSee('Loại phòng')
            ->assertSee('IMAX Human Label');

        $this->assertNotSame($roomType->name, $format->name);
    }

    public function test_staff_counter_settlement_qr_wording_and_no_digital_check_in_controls(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->paidBooking($scenario);
        $staff = $this->userWithRole('staff');
        $payment = Payment::query()->forceCreate([
            'booking_id' => $booking->id,
            'provider' => Payment::PROVIDER_COUNTER_CASH,
            'payment_method' => Payment::PROVIDER_COUNTER_CASH,
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_SUCCESS,
            'settled_at' => now(),
            'settled_by_user_id' => $staff->id,
            'paid_at' => now(),
        ]);
        $session = [CinemaAccessService::SESSION_KEY => $scenario['cinema']->id];

        $this->actingAs($staff)->withSession($session)
            ->get(route('staff.tickets.index'))->assertOk()
            ->assertSee('Quét QR đơn đặt vé bằng camera')
            ->assertSee('Mã đơn đặt vé hoặc QR đơn đặt vé');
        $html = $this->withSession($session)
            ->get(route('staff.tickets.operations', $booking))->assertOk()
            ->assertSee('Đã thu tiền lúc')
            ->assertSee($payment->settled_at->format('d/m/Y H:i:s'))
            ->getContent();

        foreach (['data-check-in-action', 'data-attendance-action', 'data-redeem-ticket-action'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }

    public function test_canonical_room_showtime_booking_payment_counter_and_back_link_chain(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->paidBooking($scenario);
        $payment = $this->onlinePayment($booking);
        $manager = $this->userWithRole('manager');
        $session = [CinemaAccessService::SESSION_KEY => $scenario['cinema']->id];

        $this->actingAs($manager)->withSession($session)
            ->get(route('admin.rooms.show', $scenario['room']))->assertOk()
            ->assertSee(route('admin.showtimes.show', $scenario['showtime']), false);
        $this->withSession($session)
            ->get(route('admin.showtimes.show', $scenario['showtime']))->assertOk()
            ->assertSee(route('admin.bookings.show', $booking), false);
        $this->withSession($session)
            ->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee(route('admin.payments.show', $payment), false)
            ->assertSee(route('staff.tickets.operations', $booking), false);
        $this->withSession($session)
            ->get(route('admin.payments.show', $payment))->assertOk()
            ->assertSee(route('admin.bookings.show', $booking), false);
        $this->withSession($session)
            ->get(route('staff.tickets.operations', $booking))->assertOk();
    }

    public function test_operational_handoff_queries_are_bounded_with_many_related_rows(): void
    {
        $scenario = $this->bookingScenario(false);
        $booking = $this->paidBooking($scenario);
        foreach (range(1, 9) as $attempt) {
            $this->pendingPayment($booking, [
                'amount' => (int) $booking->total_amount,
                'status' => Payment::STATUS_FAILED,
                'failed_at' => now(),
                'app_trans_id' => now('Asia/Ho_Chi_Minh')->format('ymd').'_'.str_pad((string) $attempt, 24, '0'),
            ]);
        }
        foreach (range(1, 7) as $number) {
            Showtime::query()->create([
                'movie_id' => $scenario['movie']->id,
                'cinema_id' => $scenario['cinema']->id,
                'room_id' => $scenario['room']->id,
                'room_layout_id' => $scenario['layout']->id,
                'presentation_format_id' => $scenario['showtime']->presentation_format_id,
                'show_date' => now()->addDays(5 + $number)->toDateString(),
                'show_time' => '19:00:00',
                'status' => 'active',
            ]);
            SeatIncident::query()->create([
                'cinema_id' => $scenario['cinema']->id,
                'room_id' => $scenario['room']->id,
                'status' => SeatIncident::STATUS_OPEN,
                'reason' => SeatIncident::REASON_OTHER,
                'note' => 'Bounded query fixture '.$number,
            ]);
        }
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');
        $session = [CinemaAccessService::SESSION_KEY => $scenario['cinema']->id];
        $counts = [
            'room' => $this->countQueries(fn () => $this->actingAs($manager)->withSession($session)
                ->get(route('admin.rooms.show', $scenario['room']))->assertOk()),
            'booking' => $this->countQueries(fn () => $this->actingAs($manager)->withSession($session)
                ->get(route('admin.bookings.show', $booking))->assertOk()),
            'staff' => $this->countQueries(fn () => $this->actingAs($staff)->withSession($session)
                ->get(route('staff.tickets.operations', $booking))->assertOk()),
        ];

        foreach ($counts as $surface => $count) {
            $this->assertLessThanOrEqual(30, $count, $surface.' query budget exceeded: '.json_encode($counts));
        }
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'OPERATIONAL_HANDOFF_QUERY_COUNTS='.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    private function paidBooking(array $scenario, int $seatCount = 1): Booking
    {
        $seatIds = $scenario['seats']->where('status', 'active')->take($seatCount)->pluck('id')->all();
        $booking = $this->reserve($scenario, $seatIds)->booking;
        $booking->forceFill([
            'booking_status' => 'paid',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ])->save();

        return $booking->fresh();
    }

    private function onlinePayment(Booking $booking): Payment
    {
        return Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => Payment::STATUS_SUCCESS,
            'transaction_code' => 'VNP-'.str()->upper(str()->random(12)),
            'verified_at' => now(),
            'provider_paid_at' => now(),
            'paid_at' => now(),
        ]);
    }

    private function incidentImpact(SeatIncident $incident, $bookingSeat): SeatIncidentImpact
    {
        return SeatIncidentImpact::query()->create([
            'seat_incident_id' => $incident->id,
            'booking_seat_id' => $bookingSeat->id,
            'detected_classification' => SeatIncidentImpact::PAID,
            'resolution_status' => SeatIncidentImpact::RESOLUTION_UNRESOLVED,
            'detected_at' => now(),
        ]);
    }

    private function foreignShowtime(Room $room): Showtime
    {
        $movie = Movie::query()->create([
            'title' => 'Foreign Operational Movie',
            'slug' => 'foreign-operational-'.str()->lower(str()->random(8)),
            'duration' => 90,
            'status' => 'now_showing',
        ]);
        $layout = $this->publishedRoomLayoutFixture($room);
        $format = $this->presentationFormatFixture($movie, $room);

        return Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $format->id,
            'show_date' => now()->addDays(6)->toDateString(),
            'show_time' => '20:00:00',
            'status' => 'active',
        ]);
    }

    private function foreignBooking(): Booking
    {
        $cinema = Cinema::factory()->create(['is_primary' => false, 'status' => 'active']);
        $room = Room::factory()->create(['cinema_id' => $cinema->id]);
        $showtime = $this->foreignShowtime($room);

        return Booking::query()->create([
            'showtime_id' => $showtime->id,
            'booking_code' => 'FOREIGN-HANDOFF-'.str()->upper(str()->random(8)),
            'customer_email' => 'foreign-handoff@example.test',
            'total_amount' => 50_000,
            'seat_subtotal' => 50_000,
            'food_subtotal' => 0,
            'gross_amount' => 50_000,
            'promotion_discount_amount' => 0,
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    private function countQueries(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $operation();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
