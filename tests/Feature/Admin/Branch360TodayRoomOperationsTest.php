<?php

namespace Tests\Feature\Admin;

use App\Models\Cinema;
use App\Models\CinemaOperatingHour;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomType;
use App\Models\SeatIncident;
use App\Models\Showtime;
use App\Models\User;
use App\Services\Admin\Branch360ReadModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Branch360TodayRoomOperationsTest extends TestCase
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
        $this->cinema->update(['timezone' => 'Asia/Ho_Chi_Minh']);
        $this->setLocalNow('2026-08-13 00:30:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_today_summary_keeps_business_date_separate_from_physical_playing_now(): void
    {
        $manager = $this->userWithRole('manager');
        $previousRoom = $this->room('P01');
        $todayPlayingRoom = $this->room('P02');
        $completedRoom = $this->room('P03');
        $upcomingRoom = $this->room('P04');
        $cancelledRoom = $this->room('P05');
        $previous = $this->showtime($previousRoom, '2026-08-12', '23:30:00', 120, '2D');
        $todayPlaying = $this->showtime($todayPlayingRoom, '2026-08-13', '00:15:00', 60, '3D');
        $completed = $this->showtime($completedRoom, '2026-08-13', '00:00:00', 15, '2D');
        $upcoming = $this->showtime($upcomingRoom, '2026-08-13', '02:00:00', 90, '3D');
        $cancelled = $this->showtime($cancelledRoom, '2026-08-13', '03:00:00', 90, '2D', 'cancelled');
        CinemaOperatingHour::query()->create([
            'cinema_id' => $this->cinema->id,
            'day_of_week' => CarbonImmutable::now($this->cinema->timezone)->dayOfWeekIso,
            'is_closed' => true,
        ]);

        $snapshot = $this->snapshot($manager);

        $this->assertSame('2026-08-13', $snapshot['todayOperations']['businessDate']);
        $this->assertSame([
            'upcoming' => 1,
            'playing' => 1,
            'completed' => 1,
            'cancelled' => 1,
        ], $snapshot['todayOperations']['counts']);
        $this->assertSame([$previous->id, $todayPlaying->id], collect($snapshot['playingNow']['items'])->pluck('showtimeId')->all());
        $this->assertSame(2, $snapshot['playingNow']['total']);
        $this->assertSame([$upcoming->id], collect($snapshot['upcomingSoon']['items'])->pluck('showtimeId')->all());
        $this->assertSame('SHOWING', collect($snapshot['roomOperations'])->firstWhere('roomId', $previousRoom->id)['operationalState']);
        $this->assertSame('closed', $snapshot['header']['operatingHours']['key']);
        $this->assertNotNull(collect($snapshot['actionQueue']['items'])->firstWhere('type', 'closed_day_schedule_conflict'));
        $this->assertNotContains($previous->id, [$completed->id, $cancelled->id]);
    }

    public function test_room_state_boundaries_use_authoritative_start_end_and_room_ready(): void
    {
        $manager = $this->userWithRole('manager');
        $room = $this->room('BOUNDARY', cleaningMinutes: 15);
        $showtime = $this->showtime($room, '2026-08-13', '10:00:00', 60, '2D');

        $this->setLocalNow('2026-08-13 09:59:59');
        $this->assertRoomState($manager, $room, 'READY');

        $this->setLocalNow('2026-08-13 10:00:00');
        $showing = $this->assertRoomState($manager, $room, 'SHOWING');
        $this->assertSame($showtime->id, $showing['currentShowtime']['showtimeId']);

        $this->setLocalNow('2026-08-13 10:59:59');
        $this->assertRoomState($manager, $room, 'SHOWING');

        $this->setLocalNow('2026-08-13 11:00:00');
        $cleaning = $this->assertRoomState($manager, $room, 'CLEANING');
        $this->assertSame('11:15', $cleaning['cleaningReadyAt']->format('H:i'));
        $this->assertSame(1, $this->snapshot($manager)['todayOperations']['counts']['completed']);

        $this->setLocalNow('2026-08-13 11:14:59');
        $this->assertRoomState($manager, $room, 'CLEANING');

        $this->setLocalNow('2026-08-13 11:15:00');
        $this->assertRoomState($manager, $room, 'READY');
    }

    public function test_zero_cleaning_is_ready_at_exact_movie_end(): void
    {
        $manager = $this->userWithRole('manager');
        $room = $this->room('ZERO', cleaningMinutes: 0);
        $this->showtime($room, '2026-08-13', '10:00:00', 60, '2D');
        $this->setLocalNow('2026-08-13 11:00:00');

        $projection = $this->assertRoomState($manager, $room, 'READY');

        $this->assertNull($projection['cleaningReadyAt']);
        $this->assertSame(1, $this->snapshot($manager)['todayOperations']['counts']['completed']);
    }

    public function test_upcoming_horizon_is_now_exclusive_and_120_minutes_inclusive(): void
    {
        $manager = $this->userWithRole('manager');
        $this->setLocalNow('2026-08-13 10:00:00');
        $roomA = $this->room('H01');
        $roomB = $this->room('H02');
        $roomC = $this->room('H03');
        $included119 = $this->showtime($roomB, '2026-08-13', '11:59:00', 90, '2D');
        $included120 = $this->showtime($roomA, '2026-08-13', '12:00:00', 90, '3D');
        $excluded = $this->showtime($roomC, '2026-08-13', '12:01:00', 90, '2D');
        $cancelled = $this->showtime($roomA, '2026-08-13', '10:30:00', 30, '2D', 'cancelled');

        $snapshot = $this->snapshot($manager);

        $this->assertSame([$included119->id, $included120->id], collect($snapshot['upcomingSoon']['items'])->pluck('showtimeId')->all());
        $this->assertSame('12:00', $snapshot['upcomingSoon']['untilAt']->format('H:i'));
        $this->assertSame(3, $snapshot['todayOperations']['counts']['upcoming']);
        $this->assertSame(1, $snapshot['todayOperations']['counts']['cancelled']);
        $this->assertNotContains($excluded->id, collect($snapshot['upcomingSoon']['items'])->pluck('showtimeId')->all());
        $this->assertNotContains($cancelled->id, collect($snapshot['upcomingSoon']['items'])->pluck('showtimeId')->all());
    }

    public function test_upcoming_horizon_includes_exact_119_59_and_120_00_but_excludes_beyond_boundary(): void
    {
        $manager = $this->userWithRole('manager');
        $room = $this->room('EDGE');
        $edge = $this->showtime($room, '2026-08-13', '12:00:00', 90, '2D');

        $this->setLocalNow('2026-08-13 10:00:01');
        $this->assertContains($edge->id, collect($this->snapshot($manager)['upcomingSoon']['items'])->pluck('showtimeId')->all());

        $this->setLocalNow('2026-08-13 10:00:00');
        $this->assertContains($edge->id, collect($this->snapshot($manager)['upcomingSoon']['items'])->pluck('showtimeId')->all());

        $this->setLocalNow('2026-08-13 09:59:59');
        $this->assertNotContains($edge->id, collect($this->snapshot($manager)['upcomingSoon']['items'])->pluck('showtimeId')->all());
    }

    public function test_true_room_next_show_is_not_limited_to_horizon_and_excludes_cancelled(): void
    {
        $manager = $this->userWithRole('manager');
        $this->setLocalNow('2026-08-13 10:00:00');
        $farRoom = $this->room('NEXT1', roomTypeCode: 'STANDARD');
        $tomorrowRoom = $this->room('NEXT2', roomTypeCode: 'STANDARD');
        $cancelled = $this->showtime($farRoom, '2026-08-13', '11:00:00', 30, '2D', 'cancelled');
        $far = $this->showtime($farRoom, '2026-08-13', '14:00:00', 90, '3D');
        $tomorrow = $this->showtime($tomorrowRoom, '2026-08-14', '09:00:00', 90, '3D');

        $snapshot = $this->snapshot($manager);
        $rooms = collect($snapshot['roomOperations']);

        $this->assertSame([], $snapshot['upcomingSoon']['items']);
        $this->assertSame($far->id, $rooms->firstWhere('roomId', $farRoom->id)['nextShowtime']['showtimeId']);
        $this->assertSame('STANDARD', $rooms->firstWhere('roomId', $farRoom->id)['roomType']);
        $this->assertSame('3D', $rooms->firstWhere('roomId', $farRoom->id)['nextShowtime']['formatName']);
        $this->assertSame($tomorrow->id, $rooms->firstWhere('roomId', $tomorrowRoom->id)['nextShowtime']['showtimeId']);
        $this->assertSame('14/08/2026 09:00', $rooms->firstWhere('roomId', $tomorrowRoom->id)['nextShowtime']['startsAt']->format('d/m/Y H:i'));
        $this->assertNotSame($cancelled->id, $rooms->firstWhere('roomId', $farRoom->id)['nextShowtime']['showtimeId']);
    }

    public function test_room_type_format_and_warning_overlays_remain_separate_from_schedule_state(): void
    {
        $manager = $this->userWithRole('manager');
        $this->setLocalNow('2026-08-13 10:30:00');
        $showingRoom = $this->room('IMAX1', roomTypeCode: 'IMAX');
        $incidentRoom = $this->room('WARN1');
        $missingLayoutRoom = $this->room('WARN2', publishedLayout: false);
        $inactiveRoom = $this->room('OFF1', status: 'inactive');
        $inactiveQuiet = $this->room('OFF2', status: 'inactive');
        $healthyRoom = $this->room('OK1');
        $current = $this->showtime($showingRoom, '2026-08-13', '10:00:00', 90, '2D');
        $next = $this->showtime($showingRoom, '2026-08-13', '13:00:00', 90, '3D');
        $inactiveNext = $this->showtime($inactiveRoom, '2026-08-13', '14:00:00', 90, '2D');
        SeatIncident::query()->create([
            'cinema_id' => $this->cinema->id,
            'room_id' => $incidentRoom->id,
            'status' => SeatIncident::STATUS_OPEN,
            'reason' => SeatIncident::REASON_BROKEN,
        ]);

        $rooms = collect($this->snapshot($manager)['roomOperations']);
        $showing = $rooms->firstWhere('roomId', $showingRoom->id);
        $incident = $rooms->firstWhere('roomId', $incidentRoom->id);
        $missing = $rooms->firstWhere('roomId', $missingLayoutRoom->id);
        $inactive = $rooms->firstWhere('roomId', $inactiveRoom->id);
        $quietInactive = $rooms->firstWhere('roomId', $inactiveQuiet->id);
        $healthy = $rooms->firstWhere('roomId', $healthyRoom->id);

        $this->assertSame('IMAX', $showing['roomType']);
        $this->assertSame('2D', $showing['currentShowtime']['formatName']);
        $this->assertSame('3D', $showing['nextShowtime']['formatName']);
        $this->assertSame($current->id, $showing['currentShowtime']['showtimeId']);
        $this->assertSame($next->id, $showing['nextShowtime']['showtimeId']);
        $this->assertSame('READY', $incident['operationalState']);
        $this->assertSame(1, $incident['openIncidentCount']);
        $this->assertNotNull($incident['incidentUrl']);
        $this->assertTrue($missing['layoutWarning']);
        $this->assertNotNull($missing['layoutUrl']);
        $this->assertSame('INACTIVE', $inactive['operationalState']);
        $this->assertTrue($inactive['futureShowDrift']);
        $this->assertSame($inactiveNext->id, $inactive['nextShowtime']['showtimeId']);
        $this->assertSame('INACTIVE', $quietInactive['operationalState']);
        $this->assertFalse($quietInactive['futureShowDrift']);
        $this->assertNull($quietInactive['nextShowtime']);
        $this->assertSame('READY', $healthy['operationalState']);
        $this->assertSame(0, $healthy['openIncidentCount']);
        $this->assertFalse($healthy['layoutWarning']);
        $this->assertNull($healthy['nextShowtime']);
    }

    public function test_lists_and_rooms_use_deterministic_ordering(): void
    {
        $manager = $this->userWithRole('manager');
        $this->setLocalNow('2026-08-13 10:30:00');
        $roomB = $this->room('B02');
        $roomA = $this->room('A01');
        $playingB = $this->showtime($roomB, '2026-08-13', '10:00:00', 90, '2D');
        $playingA = $this->showtime($roomA, '2026-08-13', '10:00:00', 90, '2D');
        $soonB = $this->showtime($roomB, '2026-08-13', '12:00:00', 90, '2D');
        $soonA = $this->showtime($roomA, '2026-08-13', '12:00:00', 90, '2D');

        $snapshot = $this->snapshot($manager);

        $expectedPlaying = collect([$playingB, $playingA])->sortBy(fn (Showtime $showtime) => [$showtime->room_id, $showtime->id])->pluck('id')->all();
        $expectedSoon = collect([$soonB, $soonA])->sortBy(fn (Showtime $showtime) => [$showtime->room_id, $showtime->id])->pluck('id')->all();
        $this->assertSame($expectedPlaying, collect($snapshot['playingNow']['items'])->pluck('showtimeId')->all());
        $this->assertSame($expectedSoon, collect($snapshot['upcomingSoon']['items'])->pluck('showtimeId')->all());
        $this->assertSame(['A01', 'B02'], collect($snapshot['roomOperations'])->pluck('code')->all());
    }

    public function test_snapshot_is_read_only_and_query_count_does_not_scale_per_room_showtime_or_incident(): void
    {
        $manager = $this->userWithRole('manager');
        $zeroRooms = $this->queryCount(fn () => $this->snapshot($manager));
        $firstRoom = $this->room('Q01');
        $oneRoom = $this->queryCount(fn () => $this->snapshot($manager));
        $rooms = collect([$firstRoom]);
        foreach (range(2, 10) as $index) {
            $rooms->push($this->room('Q'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)));
        }
        $tenRooms = $this->queryCount(fn () => $this->snapshot($manager));
        foreach ($rooms as $index => $room) {
            $this->showtime($room, '2026-08-13', sprintf('%02d:00:00', 12 + $index), 45, '2D');
            $this->showtime($room, '2026-08-14', sprintf('%02d:00:00', 8 + $index), 45, '3D');
        }
        $manyShowtimes = $this->queryCount(fn () => $this->snapshot($manager));
        foreach ($rooms as $room) {
            SeatIncident::query()->create([
                'cinema_id' => $this->cinema->id,
                'room_id' => $room->id,
                'status' => SeatIncident::STATUS_OPEN,
                'reason' => SeatIncident::REASON_MAINTENANCE,
            ]);
        }
        $manyIncidents = $this->queryCount(fn () => $this->snapshot($manager));

        $diagnostic = "zero={$zeroRooms}; one={$oneRoom}; ten={$tenRooms}; showtimes={$manyShowtimes}; incidents={$manyIncidents}";
        $this->assertSame($oneRoom, $tenRooms, $diagnostic);
        $this->assertLessThanOrEqual($tenRooms + 6, $manyShowtimes, $diagnostic);
        $this->assertSame($manyShowtimes, $manyIncidents, $diagnostic);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->snapshot($manager);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertStringStartsWith('select', strtolower(ltrim($query['query'])));
        }

        if (env('REPORT_QUERY_COUNTS')) {
            fwrite(STDOUT, "PHASE5C_QUERY_COUNTS=zero_rooms:{$zeroRooms},one_room:{$oneRoom},ten_rooms:{$tenRooms},many_showtimes:{$manyShowtimes},many_incidents:{$manyIncidents}".PHP_EOL);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(User $actor): array
    {
        $this->actingAs($actor);

        return app(Branch360ReadModel::class)->snapshot($this->cinema->fresh(), $actor);
    }

    /** @return array<string, mixed> */
    private function assertRoomState(User $actor, Room $room, string $expected): array
    {
        $projection = collect($this->snapshot($actor)['roomOperations'])->firstWhere('roomId', $room->id);
        $this->assertSame($expected, $projection['operationalState']);

        return $projection;
    }

    private function room(
        string $code,
        string $status = 'active',
        string $roomTypeCode = 'STANDARD',
        int $cleaningMinutes = 15,
        bool $publishedLayout = true,
    ): Room {
        $this->sequence++;
        $roomType = RoomType::query()->firstOrCreate(['code' => $roomTypeCode], [
            'name' => $roomTypeCode,
            'is_active' => true,
            'sort_order' => $this->sequence,
        ]);
        $room = Room::query()->create([
            'cinema_id' => $this->cinema->id,
            'room_type_id' => $roomType->id,
            'room_type' => $roomTypeCode,
            'code' => $code,
            'name' => 'Room '.$code,
            'total_seats' => 0,
            'cleaning_buffer_minutes' => $cleaningMinutes,
            'status' => $status,
        ]);
        if ($publishedLayout) {
            RoomLayout::query()->create([
                'room_id' => $room->id,
                'version' => 1,
                'name' => 'Layout '.$code,
                'rows' => 1,
                'columns' => 1,
                'status' => 'published',
                'published_at' => now(),
            ]);
        }

        return $room;
    }

    private function showtime(
        Room $room,
        string $date,
        string $time,
        int $duration,
        string $formatCode,
        string $status = 'active',
    ): Showtime {
        $this->sequence++;
        $movie = Movie::query()->create([
            'title' => 'Operational Movie '.$this->sequence,
            'slug' => 'operational-movie-'.$this->sequence,
            'duration' => $duration,
            'status' => 'now_showing',
        ]);
        $format = PresentationFormat::query()->firstOrCreate(['code' => 'OPS_'.$formatCode], [
            'name' => $formatCode,
            'is_active' => true,
            'sort_order' => $this->sequence,
        ]);
        $movie->supportedPresentationFormats()->syncWithoutDetaching($format);
        $room->presentationCapabilities()->syncWithoutDetaching($format);

        return Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $this->cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => RoomLayout::query()->where('room_id', $room->id)->where('status', 'published')->value('id'),
            'presentation_format_id' => $format->id,
            'show_date' => $date,
            'show_time' => $time,
            'price' => 50_000,
            'status' => $status,
        ]);
    }

    private function setLocalNow(string $localDateTime): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($localDateTime, $this->cinema->timezone)->utc());
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
