<?php

namespace Tests\Feature\Admin;

use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Cinema;
use App\Models\CinemaOperatingHour;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Models\SeatIncidentImpact;
use App\Models\SeatIncidentResolution;
use App\Models\SeatIncidentSeat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\Admin\Branch360ReadModel;
use App\Services\CinemaAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Branch360ReadModelTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->startSession();
        request()->setLaravelSession($this->app['session']->driver());
        $this->seedRbac();
        $this->cinema = Cinema::query()->active()->primary()->firstOrFail();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 00:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_header_uses_cinema_local_time_and_distinguishes_missing_closed_and_configured_hours(): void
    {
        $admin = $this->userWithRole('admin');
        $this->cinema->update(['timezone' => 'Asia/Tokyo', 'address' => str_repeat('A', 180)]);

        $snapshot = $this->snapshot($admin);

        $this->assertSame('09:30', $snapshot['header']['localTime']->format('H:i'));
        $this->assertSame('not_configured', $snapshot['header']['operatingHours']['key']);
        $this->assertSame('active', $snapshot['header']['branchStatus']['key']);
        $this->assertLessThanOrEqual(120, mb_strlen($snapshot['header']['shortAddress']));
        $this->assertSame(0, $snapshot['actionQueue']['total']);

        CinemaOperatingHour::query()->create([
            'cinema_id' => $this->cinema->id,
            'day_of_week' => $snapshot['header']['localTime']->dayOfWeekIso,
            'is_closed' => true,
        ]);
        $snapshot = $this->snapshot($admin);
        $this->assertSame('closed', $snapshot['header']['operatingHours']['key']);

        CinemaOperatingHour::query()->where('cinema_id', $this->cinema->id)->update([
            'is_closed' => false,
            'opens_at' => '08:00:00',
            'latest_show_start_at' => '23:00:00',
        ]);
        $snapshot = $this->snapshot($admin);
        $this->assertSame('configured', $snapshot['header']['operatingHours']['key']);
        $this->assertStringContainsString('Nhận suất chiếu cuối 23:00', $snapshot['header']['operatingHours']['detail']);

        $this->cinema->update(['status' => 'inactive']);
        $this->assertSame('inactive', $this->snapshot($admin)['header']['branchStatus']['key']);
        $this->cinema->update(['archived_at' => now()]);
        $this->assertSame('archived', $this->snapshot($admin)['header']['branchStatus']['key']);
    }

    public function test_incident_projection_uses_upcoming_lifecycle_and_highest_action_per_business_case(): void
    {
        $manager = $this->userWithRole('manager');
        $refund = $this->incidentScenario('2026-08-14', '18:00:00');
        $this->resolution($refund, SeatIncidentResolution::TYPE_REQUIRES_REFUND);
        $replacement = $this->incidentScenario('2026-08-14', '17:00:00', printed: true);
        $this->resolution($replacement, SeatIncidentResolution::TYPE_EQUIVALENT, reprintRequired: true);
        $paid = $this->incidentScenario('2026-08-14', '16:00:00');
        $future = $this->incidentScenario('2026-08-14', '15:00:00', paid: false);

        $items = collect($this->snapshot($manager)['actionQueue']['items']);

        $this->assertSame([
            'incident_requires_refund',
            'incident_replacement_print',
            'incident_paid_impact',
            'incident_upcoming_impact',
        ], $items->pluck('type')->all());
        $this->assertCount(1, $items->where('context.incidentId', $refund['incident']->id));
        $this->assertStringContainsString('cần xử lý hoàn tiền', $items->first()['message']);
        $this->assertStringNotContainsString('Đã hoàn tiền', $items->first()['message']);
        $this->assertStringNotContainsString('Refunded', $items->first()['message']);
        $this->assertSame(route('admin.rooms.seat-incidents.show', [$replacement['room'], $replacement['incident']]), $items[1]['actionUrl']);
        $this->assertSame($paid['booking']->booking_code, $items[2]['context']['bookingCode']);
        $this->assertSame($future['showtime']->id, $items[3]['context']['showtimeId']);
    }

    public function test_cancelled_completed_and_previous_date_playing_incident_impacts_are_not_p0(): void
    {
        $manager = $this->userWithRole('manager');
        $cancelled = $this->incidentScenario('2026-08-14', '10:00:00');
        $cancelled['showtime']->update(['status' => 'cancelled']);
        $this->incidentScenario('2026-08-12', '18:00:00');
        $playing = $this->incidentScenario('2026-08-12', '23:30:00', duration: 600);

        $items = collect($this->snapshot($manager)['actionQueue']['items']);

        $this->assertSame(0, $items->where('context.incidentId', $cancelled['incident']->id)->count());
        $this->assertSame(0, $items->where('context.incidentId', $playing['incident']->id)->count());
        $this->assertSame(0, $items->count());
    }

    public function test_payment_tasks_are_branch_scoped_permission_aware_and_exclude_processing(): void
    {
        $manager = $this->userWithRole('manager');
        $unresolved = $this->paymentScenario($this->cinema, Payment::STATUS_UNRESOLVED);
        $review = $this->paymentScenario($this->cinema, Payment::STATUS_REVIEW);
        $this->paymentScenario($this->cinema, Payment::STATUS_PROCESSING);
        $foreign = Cinema::factory()->create(['status' => 'active', 'archived_at' => null]);
        $this->paymentScenario($foreign, Payment::STATUS_REVIEW);

        $items = collect($this->snapshot($manager)['actionQueue']['items']);

        $this->assertSame(['payment_review', 'payment_unresolved'], $items->pluck('type')->sort()->values()->all());
        $this->assertTrue($items->every(fn (array $item): bool => $item['actionUrl'] === route('admin.payment-reconciliation.index')));
        $this->assertTrue($items->pluck('context.bookingCode')->contains($unresolved['booking']->booking_code));
        $this->assertTrue($items->pluck('context.bookingCode')->contains($review['booking']->booking_code));

        $manager->role->permissions()->detach(Permission::query()->where('slug', 'payments.reconcile')->value('id'));
        $manager->unsetRelation('role');
        $this->assertSame(0, collect($this->snapshot($manager)['actionQueue']['items'])->whereIn('type', [
            'payment_unresolved', 'payment_review',
        ])->count());
    }

    public function test_pending_booking_is_not_p0_and_customer_data_is_not_projected(): void
    {
        $manager = $this->userWithRole('manager');
        $scenario = $this->showtimeScenario($this->cinema, '2026-08-14', '18:00:00');
        Booking::query()->create([
            'showtime_id' => $scenario['showtime']->id,
            'booking_code' => 'PENDING-NORMAL',
            'customer_name' => 'Private Customer',
            'customer_email' => 'private@example.test',
            'customer_phone' => '0900000000',
            'total_amount' => 50_000,
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
            'expires_at' => now()->addMinutes(15),
        ]);

        $snapshot = $this->snapshot($manager);

        $this->assertSame(0, $snapshot['actionQueue']['total']);
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('private@example.test', $json);
        $this->assertStringNotContainsString('0900000000', $json);
    }

    public function test_inactive_room_and_closed_day_conflicts_use_authoritative_future_and_business_date_rules(): void
    {
        $manager = $this->userWithRole('manager');
        $inactive = $this->showtimeScenario($this->cinema, '2026-08-14', '18:00:00', roomStatus: 'inactive');
        $cancelled = $this->showtimeScenario($this->cinema, '2026-08-14', '19:00:00', roomStatus: 'inactive');
        $cancelled['showtime']->update(['status' => 'cancelled']);
        $this->showtimeScenario($this->cinema, '2026-08-12', '18:00:00', roomStatus: 'inactive');
        CinemaOperatingHour::query()->create([
            'cinema_id' => $this->cinema->id,
            'day_of_week' => CarbonImmutable::now($this->cinema->timezone)->dayOfWeekIso,
            'is_closed' => true,
        ]);
        $this->showtimeScenario($this->cinema, '2026-08-13', '03:00:00');
        $playing = $this->showtimeScenario($this->cinema, '2026-08-13', '06:30:00', duration: 120);
        $this->showtimeScenario($this->cinema, '2026-08-13', '10:00:00');

        $items = collect($this->snapshot($manager)['actionQueue']['items']);

        $roomTask = $items->firstWhere('type', 'inactive_room_future_show');
        $this->assertSame($inactive['room']->id, $roomTask['context']['roomId']);
        $this->assertSame(route('admin.rooms.show', $inactive['room']), $roomTask['actionUrl']);
        $this->assertSame(1, $items->where('type', 'inactive_room_future_show')->count());
        $closedTask = $items->firstWhere('type', 'closed_day_schedule_conflict');
        $this->assertSame($playing['showtime']->id, $closedTask['context']['showtimeId']);
        $this->assertSame(2, $closedTask['context']['showtimeCount']);
        $this->assertStringContainsString('đóng cửa hôm nay', $closedTask['message']);
    }

    public function test_snapshot_executes_read_queries_only(): void
    {
        $manager = $this->userWithRole('manager');
        $this->incidentScenario('2026-08-14', '18:00:00');
        $this->paymentScenario($this->cinema, Payment::STATUS_REVIEW);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->snapshot($manager);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->assertStringStartsWith('select', strtolower(ltrim($query['query'])));
        }
    }

    public function test_queue_is_deterministically_ordered_bounded_and_query_count_does_not_scale_per_item(): void
    {
        $manager = $this->userWithRole('manager');
        $small = $this->incidentScenario('2026-08-14', '18:00:00');
        $this->resolution($small, SeatIncidentResolution::TYPE_REQUIRES_REFUND);
        $smallCount = $this->queryCount(fn () => $this->snapshot($manager));

        foreach (range(1, 11) as $index) {
            $scenario = $this->incidentScenario('2026-08-15', sprintf('%02d:00:00', 6 + $index), paid: false);
            if ($index % 2 === 0) {
                $this->resolution($scenario, SeatIncidentResolution::TYPE_REQUIRES_REFUND);
            }
        }

        $manyCount = $this->queryCount(fn () => $this->snapshot($manager));
        $queue = $this->snapshot($manager)['actionQueue'];

        $this->assertSame(12, $queue['total']);
        $this->assertSame(Branch360ReadModel::PRESENTATION_LIMIT, count($queue['items']));
        $this->assertSame(12 - Branch360ReadModel::PRESENTATION_LIMIT, $queue['remaining']);
        $this->assertLessThanOrEqual($smallCount + 1, $manyCount, "Small={$smallCount}; many={$manyCount}");
        $this->assertSame($queue['items'], collect($queue['items'])->sortBy([
            ['priorityRank', 'asc'], ['relevantAt', 'asc'], ['key', 'asc'],
        ])->values()->all());
    }

    public function test_every_projected_action_url_is_an_existing_workflow_accessible_to_the_manager(): void
    {
        $manager = $this->userWithRole('manager');
        $refund = $this->incidentScenario('2026-08-14', '16:00:00');
        $this->resolution($refund, SeatIncidentResolution::TYPE_REQUIRES_REFUND);
        $replacement = $this->incidentScenario('2026-08-14', '17:00:00', printed: true);
        $this->resolution($replacement, SeatIncidentResolution::TYPE_EQUIVALENT, reprintRequired: true);
        $this->incidentScenario('2026-08-14', '18:00:00');
        $this->paymentScenario($this->cinema, Payment::STATUS_REVIEW);
        $this->showtimeScenario($this->cinema, '2026-08-14', '19:00:00', roomStatus: 'inactive');
        CinemaOperatingHour::query()->create([
            'cinema_id' => $this->cinema->id,
            'day_of_week' => CarbonImmutable::now($this->cinema->timezone)->dayOfWeekIso,
            'is_closed' => true,
        ]);
        $this->showtimeScenario($this->cinema, '2026-08-13', '10:00:00');

        $actionUrls = collect($this->snapshot($manager)['actionQueue']['items'])
            ->pluck('actionUrl')
            ->unique()
            ->values();

        $this->assertGreaterThanOrEqual(5, $actionUrls->count());
        foreach ($actionUrls as $actionUrl) {
            $this->actingAs($manager)
                ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
                ->get($actionUrl)
                ->assertOk();
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(User $actor): array
    {
        $this->actingAs($actor);

        return app(Branch360ReadModel::class)->snapshot($this->cinema->fresh(), $actor);
    }

    /** @return array<string, mixed> */
    private function showtimeScenario(
        Cinema $cinema,
        string $date,
        string $time,
        string $roomStatus = 'active',
        int $duration = 90,
    ): array {
        $this->sequence++;
        $room = Room::query()->create([
            'cinema_id' => $cinema->id,
            'code' => 'B'.str_pad((string) $this->sequence, 3, '0', STR_PAD_LEFT),
            'name' => 'Branch 360 Room '.$this->sequence,
            'room_type' => '2D',
            'total_seats' => 1,
            'status' => $roomStatus,
        ]);
        $seat = Seat::query()->create([
            'room_id' => $room->id,
            'row' => 'A',
            'number' => 1,
            'seat_code' => 'A1',
            'type' => 'normal',
            'status' => 'active',
        ]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Branch 360 Layout '.$this->sequence,
            'rows' => 1,
            'columns' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $layout->cells()->create([
            'x_position' => 1,
            'y_position' => 1,
            'cell_type' => 'seat',
            'seat_id' => $seat->id,
        ]);
        $movie = Movie::query()->create([
            'title' => 'Branch 360 Movie '.$this->sequence,
            'slug' => 'branch-360-movie-'.$this->sequence,
            'duration' => $duration,
            'status' => 'now_showing',
        ]);
        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id,
            'show_date' => $date,
            'show_time' => $time,
            'price' => 50_000,
            'status' => 'active',
        ]);

        return compact('cinema', 'room', 'seat', 'layout', 'movie', 'showtime');
    }

    /** @return array<string, mixed> */
    private function incidentScenario(
        string $date,
        string $time,
        bool $paid = true,
        bool $printed = false,
        int $duration = 90,
    ): array {
        $scenario = $this->showtimeScenario($this->cinema, $date, $time, duration: $duration);
        $booking = Booking::query()->create([
            'showtime_id' => $scenario['showtime']->id,
            'booking_code' => 'B360-'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'customer_name' => 'Private Branch Customer',
            'customer_email' => 'private-'.$this->sequence.'@example.test',
            'customer_phone' => '090000000'.$this->sequence,
            'total_amount' => 50_000,
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'booking_status' => $paid ? 'paid' : 'pending_payment',
            'paid_at' => $paid ? now() : null,
            'expires_at' => $paid ? null : now()->addMinutes(15),
        ]);
        $bookingSeat = BookingSeat::query()->create([
            'booking_id' => $booking->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seat']->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => 50_000,
            'pricing_unit_key' => 'seat:'.$scenario['seat']->id,
        ]);
        if ($paid) {
            Payment::createForProvider('vnpay', [
                'booking_id' => $booking->id,
                'payment_method' => 'vnpay',
                'order_code' => 'B360-PAY-'.$this->sequence,
                'amount' => 50_000,
                'currency' => 'VND',
                'status' => Payment::STATUS_SUCCESS,
                'verified_at' => now(),
                'paid_at' => now(),
            ]);
        }
        $ticket = AdmissionTicket::query()->firstOrCreate(
            ['booking_seat_id' => $bookingSeat->id],
            ['booking_id' => $booking->id, 'ticket_code' => 'B360-TICKET-'.$this->sequence],
        );
        if ($printed) {
            $ticket->forceFill(['print_count' => 1, 'last_printed_at' => now()])->save();
        }
        $incident = SeatIncident::query()->create([
            'cinema_id' => $this->cinema->id,
            'room_id' => $scenario['room']->id,
            'status' => SeatIncident::STATUS_OPEN,
            'reason' => SeatIncident::REASON_BROKEN,
        ]);
        SeatIncidentSeat::query()->create([
            'seat_incident_id' => $incident->id,
            'seat_id' => $scenario['seat']->id,
            'active_lock_key' => 'ACTIVE',
        ]);
        $impact = SeatIncidentImpact::query()->create([
            'seat_incident_id' => $incident->id,
            'booking_seat_id' => $bookingSeat->id,
            'detected_classification' => $paid ? SeatIncidentImpact::PAID : SeatIncidentImpact::RETAINED_PAYMENT,
            'resolution_status' => SeatIncidentImpact::RESOLUTION_UNRESOLVED,
            'detected_at' => now(),
        ]);

        return [...$scenario, ...compact('booking', 'bookingSeat', 'ticket', 'incident', 'impact')];
    }

    private function resolution(
        array $scenario,
        string $type,
        bool $reprintRequired = false,
    ): SeatIncidentResolution {
        return SeatIncidentResolution::query()->create([
            'seat_incident_impact_id' => $scenario['impact']->id,
            'operation_id' => fake()->uuid(),
            'resolution_type' => $type,
            'original_seat_id' => $scenario['seat']->id,
            'replacement_seat_id' => $type === SeatIncidentResolution::TYPE_REQUIRES_REFUND ? null : $scenario['seat']->id,
            'original_pre_promotion_amount' => 50_000,
            'replacement_hypothetical_amount' => $type === SeatIncidentResolution::TYPE_REQUIRES_REFUND ? null : 50_000,
            'reprint_required' => $reprintRequired,
        ]);
    }

    /** @return array<string, mixed> */
    private function paymentScenario(Cinema $cinema, string $status): array
    {
        $scenario = $this->showtimeScenario($cinema, '2026-08-14', '20:00:00');
        $booking = Booking::query()->create([
            'showtime_id' => $scenario['showtime']->id,
            'booking_code' => 'PAYMENT-'.$this->sequence,
            'customer_email' => 'hidden-'.$this->sequence.'@example.test',
            'customer_phone' => '091111111'.$this->sequence,
            'total_amount' => 50_000,
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
        ]);
        $payment = Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'ATTENTION-'.$this->sequence,
            'amount' => 50_000,
            'currency' => 'VND',
            'status' => $status,
        ]);

        return [...$scenario, ...compact('booking', 'payment')];
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
