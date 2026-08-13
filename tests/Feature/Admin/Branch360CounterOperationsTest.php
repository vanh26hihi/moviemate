<?php

namespace Tests\Feature\Admin;

use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\BookingTicketPrint;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Models\SeatIncidentImpact;
use App\Models\SeatIncidentResolution;
use App\Models\Showtime;
use App\Models\User;
use App\Services\Admin\Branch360ReadModel;
use App\Services\CinemaAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Branch360CounterOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    private Room $room;

    private Movie $movie;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->startSession();
        request()->setLaravelSession($this->app['session']->driver());
        $this->seedRbac();
        $this->cinema = Cinema::query()->active()->primary()->firstOrFail();
        $this->cinema->update(['timezone' => 'Asia/Ho_Chi_Minh']);
        $this->room = $this->room($this->cinema, 'C01');
        $this->movie = Movie::query()->create([
            'title' => 'Counter Operations Movie',
            'slug' => 'counter-operations-movie',
            'duration' => 90,
            'status' => 'now_showing',
        ]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:00:00', $this->cinema->timezone)->utc());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_authoritative_paid_evidence_and_booking_states_define_first_print_workload(): void
    {
        $manager = $this->userWithRole('manager');
        $showtime = $this->showtime('2026-08-13', '11:00:00');
        $online = $this->booking($showtime, Payment::PROVIDER_COUNTER_CASH, evidence: 'online');
        $cash = $this->booking($showtime, Payment::PROVIDER_COUNTER_CASH, evidence: 'cash');
        $internalZero = $this->booking($showtime, Payment::PROVIDER_INTERNAL_ZERO, evidence: 'internal_zero');
        $this->booking($showtime, 'vnpay', evidence: 'unverified');
        $this->booking($showtime, 'vnpay', evidence: 'review');
        $this->booking($showtime, 'vnpay', bookingStatus: 'pending_payment', paymentStatus: 'unpaid');
        $this->booking($showtime, 'vnpay', bookingStatus: 'expired', paymentStatus: 'unpaid');
        $this->booking($showtime, 'vnpay', bookingStatus: 'cancelled', paymentStatus: 'paid', evidence: 'online');

        $items = collect($this->snapshot($manager)['counterOperations']['items']);

        $this->assertEqualsCanonicalizing(
            [$online->booking_code, $cash->booking_code, $internalZero->booking_code],
            $items->pluck('bookingCode')->all(),
        );
        $this->assertSame(3, $items->count());
        $this->assertSame(3, $items->sum('unprintedTicketCount'));
        $this->assertSame(
            route('staff.tickets.operations', $online),
            $items->firstWhere('bookingCode', $online->booking_code)['actionUrl'],
        );
    }

    public function test_upcoming_lifecycle_exact_start_and_cross_midnight_use_one_cinema_local_snapshot(): void
    {
        $manager = $this->userWithRole('manager');
        $upcoming = $this->booking($this->showtime('2026-08-13', '11:00:00'), 'vnpay', evidence: 'online');
        $this->booking($this->showtime('2026-08-13', '10:00:00'), 'vnpay', evidence: 'online');
        $this->booking($this->showtime('2026-08-13', '09:30:00'), 'vnpay', evidence: 'online');
        $this->booking($this->showtime('2026-08-13', '07:00:00'), 'vnpay', evidence: 'online');
        $this->booking($this->showtime('2026-08-13', '12:00:00', status: 'cancelled'), 'vnpay', evidence: 'online');

        $items = collect($this->snapshot($manager)['counterOperations']['items']);
        $this->assertSame([$upcoming->booking_code], $items->pluck('bookingCode')->all());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 00:30:00', $this->cinema->timezone)->utc());
        $this->booking($this->showtime('2026-08-13', '23:30:00', duration: 120), 'vnpay', evidence: 'online');
        $futureAfterMidnight = $this->booking($this->showtime('2026-08-14', '00:45:00'), 'vnpay', evidence: 'online');

        $items = collect($this->snapshot($manager)['counterOperations']['items']);
        $this->assertSame([$futureAfterMidnight->booking_code], $items->pluck('bookingCode')->all());
        $this->assertSame('00:45', $items->first()['startsAt']->format('H:i'));
        $this->assertSame('Asia/Ho_Chi_Minh', $items->first()['startsAt']->timezoneName);
    }

    public function test_physical_ticket_counts_cover_couple_partial_failure_fully_printed_and_reprint_states(): void
    {
        $manager = $this->userWithRole('manager');
        $showtime = $this->showtime('2026-08-13', '11:00:00');
        $couple = $this->booking($showtime, 'vnpay', evidence: 'online', ticketPrintCounts: [1, 0], couple: true);
        $failed = $this->booking($showtime, 'vnpay', evidence: 'online');
        BookingTicketPrint::query()->create([
            'admission_ticket_id' => $failed->admissionTickets()->value('id'),
            'booking_id' => $failed->id,
            'status' => BookingTicketPrint::STATUS_RETRY_ALLOWED,
            'attempts_count' => 1,
        ]);
        $this->booking($showtime, 'vnpay', evidence: 'online', ticketPrintCounts: [1, 1]);
        $this->booking($showtime, 'vnpay', evidence: 'online', ticketPrintCounts: [2]);

        $items = collect($this->snapshot($manager)['counterOperations']['items']);
        $partial = $items->firstWhere('bookingCode', $couple->booking_code);
        $failedItem = $items->firstWhere('bookingCode', $failed->booking_code);

        $this->assertSame(2, $partial['totalTicketCount']);
        $this->assertSame(1, $partial['printedTicketCount']);
        $this->assertSame(1, $partial['unprintedTicketCount']);
        $this->assertSame("Đơn {$couple->booking_code} còn 1/2 vé chưa in.", $partial['taskMessage']);
        $this->assertSame(1, $failedItem['totalTicketCount']);
        $this->assertSame(0, $failedItem['printedTicketCount']);
        $this->assertSame(1, $failedItem['unprintedTicketCount']);
        $this->assertCount(2, $items);
    }

    public function test_queue_is_ordered_bounded_private_branch_scoped_and_links_are_authorized(): void
    {
        $manager = $this->userWithRole('manager');
        $admin = $this->userWithRole('admin');
        $sameStart = $this->showtime('2026-08-13', '11:00:00');
        $first = $this->booking($sameStart, 'vnpay', evidence: 'online');
        $second = $this->booking($sameStart, 'vnpay', evidence: 'online');
        foreach (range(1, 8) as $index) {
            $this->booking($this->showtime('2026-08-13', sprintf('%02d:00:00', 11 + $index)), 'vnpay', evidence: 'online');
        }
        $foreignCinema = Cinema::factory()->create(['status' => 'active', 'archived_at' => null, 'timezone' => 'Asia/Tokyo']);
        $foreignRoom = $this->room($foreignCinema, 'F01');
        $foreignMovie = Movie::query()->create([
            'title' => 'Foreign Private Movie',
            'slug' => 'foreign-private-movie',
            'duration' => 90,
            'status' => 'now_showing',
        ]);
        $originalRoom = $this->room;
        $originalMovie = $this->movie;
        $this->room = $foreignRoom;
        $this->movie = $foreignMovie;
        $foreign = $this->booking($this->showtime('2026-08-13', '13:00:00'), 'vnpay', evidence: 'online');
        $this->room = $originalRoom;
        $this->movie = $originalMovie;

        $counter = $this->snapshot($manager)['counterOperations'];
        $items = collect($counter['items']);
        $json = json_encode($counter, JSON_UNESCAPED_UNICODE);

        $this->assertSame(10, $counter['firstPrintBookingCount']);
        $this->assertSame(10, $counter['unprintedTicketCount']);
        $this->assertSame(Branch360ReadModel::PRESENTATION_LIMIT, $items->count());
        $this->assertSame(2, $counter['overflowCount']);
        $this->assertSame([$first->id, $second->id], $items->take(2)->pluck('bookingId')->all());
        $this->assertStringNotContainsString($foreign->booking_code, $json);
        $this->assertStringNotContainsString('private-', $json);
        $this->assertStringNotContainsString('@example.test', $json);
        $this->assertStringNotContainsString('0900', $json);
        $this->assertStringNotContainsString('GATEWAY', $json);
        $this->assertArrayNotHasKey('totalAmount', $items->first());

        foreach ([$manager, $admin] as $actor) {
            $url = $this->snapshot($actor)['counterOperations']['items'][0]['actionUrl'];
            $this->actingAs($actor)
                ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
                ->get($url)
                ->assertOk();
        }

        $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('staff.tickets.operations', $foreign))
            ->assertNotFound();

        $manager->role->permissions()->detach(Permission::query()->where('slug', 'tickets.lookup')->value('id'));
        $manager->unsetRelation('role');
        $this->assertNull($this->snapshot($manager)['counterOperations']['items'][0]['actionUrl']);
    }

    public function test_replacement_print_summary_reuses_p0_and_disappears_when_satisfied(): void
    {
        $manager = $this->userWithRole('manager');
        $booking = $this->booking($this->showtime('2026-08-13', '11:00:00'), 'vnpay', evidence: 'online', ticketPrintCounts: [1]);
        $seat = $booking->bookingSeats()->firstOrFail();
        $incident = SeatIncident::query()->create([
            'cinema_id' => $this->cinema->id,
            'room_id' => $this->room->id,
            'status' => SeatIncident::STATUS_OPEN,
            'reason' => SeatIncident::REASON_BROKEN,
        ]);
        $impact = SeatIncidentImpact::query()->create([
            'seat_incident_id' => $incident->id,
            'booking_seat_id' => $seat->id,
            'detected_classification' => SeatIncidentImpact::PAID,
            'resolution_status' => SeatIncidentImpact::RESOLUTION_UNRESOLVED,
            'detected_at' => now(),
        ]);
        $resolution = SeatIncidentResolution::query()->create([
            'seat_incident_impact_id' => $impact->id,
            'operation_id' => fake()->uuid(),
            'resolution_type' => SeatIncidentResolution::TYPE_EQUIVALENT,
            'original_seat_id' => $seat->seat_id,
            'replacement_seat_id' => $seat->seat_id,
            'original_pre_promotion_amount' => 50_000,
            'replacement_hypothetical_amount' => 50_000,
            'reprint_required' => true,
            'resolved_by_user_id' => $manager->id,
        ]);

        $snapshot = $this->snapshot($manager);
        $this->assertSame(1, $snapshot['counterOperations']['replacementPrintPendingCount']);
        $this->assertSame(1, collect($snapshot['actionQueue']['items'])->where('type', 'incident_replacement_print')->count());
        $this->assertSame([], $snapshot['counterOperations']['items']);

        $resolution->forceFill(['reprint_satisfied_at' => now()])->save();
        $snapshot = $this->snapshot($manager);
        $this->assertSame(0, $snapshot['counterOperations']['replacementPrintPendingCount']);
        $this->assertSame(0, collect($snapshot['actionQueue']['items'])->where('type', 'incident_replacement_print')->count());
    }

    public function test_ui_is_compact_has_no_digital_fulfilment_and_preserves_access_boundaries(): void
    {
        $manager = $this->userWithRole('manager');
        $booking = $this->booking($this->showtime('2026-08-13', '11:00:00'), 'vnpay', evidence: 'online', ticketPrintCounts: [1, 0], couple: true);

        $response = $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('admin.cinemas.show', $this->cinema))
            ->assertOk()
            ->assertSee('Vận hành quầy')
            ->assertSee($booking->booking_code)
            ->assertSee('Còn 1/2 vé chưa in')
            ->assertSee(route('staff.tickets.operations', $booking));

        $html = mb_strtolower($response->getContent());
        foreach (['check-in', 'attendance', 'đã quét', 'đã vào rạp', 'đã nhận đồ ăn', 'chưa nhận đồ ăn', 'redeemed', 'doanh thu', 'tỷ lệ lấp đầy'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
        $this->assertStringNotContainsString($booking->customer_email, $response->getContent());
        $this->assertStringNotContainsString('setinterval', $html);
        $this->assertStringNotContainsString('fetch(', $html);

        $this->actingAs($this->userWithRole('staff'))->get(route('admin.cinemas.show', $this->cinema))->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->get(route('admin.cinemas.show', $this->cinema))->assertForbidden();
    }

    public function test_snapshot_is_select_only_and_queries_do_not_scale_per_booking_or_ticket(): void
    {
        $manager = $this->userWithRole('manager');
        $zero = $this->queryCount(fn () => $this->snapshot($manager));
        $zeroRequest = $this->requestQueryCount($manager);
        $showtime = $this->showtime('2026-08-13', '11:00:00');
        $this->booking($showtime, 'vnpay', evidence: 'online');
        $one = $this->queryCount(fn () => $this->snapshot($manager));
        $oneRequest = $this->requestQueryCount($manager);
        foreach (range(2, 10) as $index) {
            $this->booking($showtime, 'vnpay', evidence: 'online');
        }
        $ten = $this->queryCount(fn () => $this->snapshot($manager));
        $tenRequest = $this->requestQueryCount($manager);
        foreach (Booking::query()->where('showtime_id', $showtime->id)->get() as $booking) {
            $this->addTicket($booking, $showtime, 0, false);
            $this->addTicket($booking, $showtime, 0, false);
        }
        $multiTicket = $this->queryCount(fn () => $this->snapshot($manager));
        $multiTicketRequest = $this->requestQueryCount($manager);
        $this->booking($showtime, 'vnpay', evidence: 'online', ticketPrintCounts: [0, 0], couple: true);
        $couple = $this->queryCount(fn () => $this->snapshot($manager));
        $coupleRequest = $this->requestQueryCount($manager);

        $this->assertSame($one, $ten, "zero={$zero}; one={$one}; ten={$ten}; multi={$multiTicket}");
        $this->assertSame($ten, $multiTicket, "zero={$zero}; one={$one}; ten={$ten}; multi={$multiTicket}");
        $this->assertSame($multiTicket, $couple, "multi={$multiTicket}; couple={$couple}");
        $this->assertSame($oneRequest, $tenRequest, "zero={$zeroRequest}; one={$oneRequest}; ten={$tenRequest}; multi={$multiTicketRequest}");
        $this->assertSame($tenRequest, $multiTicketRequest, "zero={$zeroRequest}; one={$oneRequest}; ten={$tenRequest}; multi={$multiTicketRequest}");
        $this->assertSame($multiTicketRequest, $coupleRequest, "multi={$multiTicketRequest}; couple={$coupleRequest}");

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->snapshot($manager);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertStringStartsWith('select', strtolower(ltrim($query['query'])));
        }

        if (env('REPORT_QUERY_COUNTS')) {
            fwrite(STDOUT, "PHASE5D_QUERY_COUNTS=zero:{$zero},one:{$one},ten:{$ten},multi_ticket:{$multiTicket},couple:{$couple}".PHP_EOL);
            fwrite(STDOUT, "PHASE5D_REQUEST_QUERY_COUNTS=zero:{$zeroRequest},one:{$oneRequest},ten:{$tenRequest},multi_ticket:{$multiTicketRequest},couple:{$coupleRequest}".PHP_EOL);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(User $actor): array
    {
        $this->actingAs($actor);

        return app(Branch360ReadModel::class)->snapshot($this->cinema->fresh(), $actor);
    }

    private function showtime(string $date, string $time, int $duration = 90, string $status = 'active'): Showtime
    {
        $this->sequence++;
        $movie = $duration === (int) $this->movie->duration ? $this->movie : Movie::query()->create([
            'title' => 'Counter Runtime '.$this->sequence,
            'slug' => 'counter-runtime-'.$this->sequence,
            'duration' => $duration,
            'status' => 'now_showing',
        ]);

        return Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $this->room->cinema_id,
            'room_id' => $this->room->id,
            'room_layout_id' => $this->room->latestPublishedLayout()->value('id'),
            'presentation_format_id' => $this->presentationFormatFixture($movie, $this->room)->id,
            'show_date' => $date,
            'show_time' => $time,
            'price' => 50_000,
            'status' => $status,
        ]);
    }

    private function booking(
        Showtime $showtime,
        string $provider,
        string $bookingStatus = 'paid',
        string $paymentStatus = 'paid',
        ?string $evidence = null,
        array $ticketPrintCounts = [0],
        bool $couple = false,
    ): Booking {
        $this->sequence++;
        $booking = Booking::query()->create([
            'showtime_id' => $showtime->id,
            'booking_code' => 'COUNTER-'.str_pad((string) $this->sequence, 5, '0', STR_PAD_LEFT),
            'customer_name' => 'Private Customer '.$this->sequence,
            'customer_email' => 'private-'.$this->sequence.'@example.test',
            'customer_phone' => '0900'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT),
            'total_amount' => 50_000,
            'payment_status' => $paymentStatus,
            'booking_status' => $bookingStatus,
            'paid_at' => $bookingStatus === 'paid' ? now() : null,
            'expires_at' => $bookingStatus === 'pending_payment' ? now()->addMinutes(15) : null,
        ]);

        if ($evidence !== null) {
            $payment = new Payment;
            $payment->forceFill([
                'booking_id' => $booking->id,
                'provider' => $evidence === 'online' || in_array($evidence, ['unverified', 'review'], true) ? 'vnpay' : $provider,
                'payment_method' => $provider,
                'order_code' => 'GATEWAY-'.$this->sequence,
                'amount' => $evidence === 'internal_zero' ? 0 : 50_000,
                'currency' => 'VND',
                'status' => $evidence === 'review' ? Payment::STATUS_REVIEW : Payment::STATUS_SUCCESS,
                'verified_at' => in_array($evidence, ['online', 'internal_zero'], true) ? now() : null,
                'settled_at' => $evidence === 'cash' ? now() : null,
                'settled_by_user_id' => $evidence === 'cash' ? $this->userWithRole('staff')->id : null,
                'paid_at' => in_array($evidence, ['online', 'cash', 'internal_zero'], true) ? now() : null,
            ]);
            $payment->save();
        }

        foreach ($ticketPrintCounts as $printCount) {
            $this->addTicket($booking, $showtime, (int) $printCount, $couple);
        }

        return $booking;
    }

    private function addTicket(Booking $booking, Showtime $showtime, int $printCount, bool $couple): AdmissionTicket
    {
        $this->sequence++;
        $seat = Seat::query()->create([
            'room_id' => $showtime->room_id,
            'row' => 'Z',
            'number' => $this->sequence,
            'seat_code' => 'Z'.$this->sequence,
            'type' => $couple ? 'couple' : 'normal',
            'status' => 'active',
        ]);
        $bookingSeat = BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $seat->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50_000,
            'pricing_unit_key' => $couple ? 'couple:'.$booking->id : 'seat:'.$seat->id,
        ]);
        $ticket = AdmissionTicket::query()->where('booking_seat_id', $bookingSeat->id)->firstOrFail();
        if ($printCount > 0) {
            $ticket->forceFill([
                'print_count' => $printCount,
                'last_printed_at' => now(),
            ])->save();
        }

        return $ticket;
    }

    private function room(Cinema $cinema, string $code): Room
    {
        $room = Room::query()->create([
            'cinema_id' => $cinema->id,
            'code' => $code,
            'name' => 'Counter Room '.$code,
            'room_type' => '2D',
            'width_mm' => 8_000,
            'length_mm' => 10_000,
            'status' => 'active',
        ]);
        RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Counter Layout '.$code,
            'rows' => 10,
            'columns' => 10,
            'status' => 'published',
            'published_at' => now(),
        ]);

        return $room;
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

    private function requestQueryCount(User $actor): int
    {
        return $this->queryCount(fn () => $this->actingAs($actor)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('admin.cinemas.show', $this->cinema))
            ->assertOk());
    }
}
