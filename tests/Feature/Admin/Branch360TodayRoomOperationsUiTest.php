<?php

namespace Tests\Feature\Admin;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomType;
use App\Models\SeatIncident;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class Branch360TodayRoomOperationsUiTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        $this->cinema = Cinema::query()->active()->primary()->firstOrFail();
        $this->cinema->update(['timezone' => 'Asia/Ho_Chi_Minh']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:30:00', $this->cinema->timezone)->utc());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_branch_360_renders_today_playing_upcoming_and_room_operations_without_phase_5c_forbidden_metrics(): void
    {
        $manager = $this->userWithRole('manager');
        $showingRoom = $this->room('UI01', 'IMAX');
        $readyRoom = $this->room('UI02', 'STANDARD');
        $missingLayoutRoom = $this->room('UI03', 'STANDARD', publishedLayout: false);
        $this->showtime($showingRoom, '2026-08-13', '10:00:00', 90, '2D');
        $this->showtime($showingRoom, '2026-08-13', '12:00:00', 90, '3D');
        SeatIncident::query()->create([
            'cinema_id' => $this->cinema->id,
            'room_id' => $readyRoom->id,
            'status' => SeatIncident::STATUS_OPEN,
            'reason' => SeatIncident::REASON_BROKEN,
        ]);

        $response = $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('admin.cinemas.show', $this->cinema))
            ->assertOk()
            ->assertSeeInOrder(['Cần xử lý', 'Vận hành hôm nay', 'Đang diễn ra', 'Sắp tới 120 phút', 'Vận hành phòng', 'Thông tin chi nhánh'])
            ->assertSee('Ngày vận hành: 13/08/2026')
            ->assertSee('Đang chiếu')
            ->assertSee('Sắp chiếu')
            ->assertSee('Đã hoàn tất')
            ->assertSee('Đã hủy')
            ->assertSee('Operational UI Movie 4')
            ->assertSee('2D')
            ->assertSee('3D')
            ->assertSee('Loại phòng: IMAX')
            ->assertSee('Sẵn sàng theo lịch')
            ->assertSee('1 sự cố đang mở')
            ->assertSee('Chưa có layout đã xuất bản')
            ->assertSee(route('admin.rooms.show', $showingRoom))
            ->assertSee(route('admin.rooms.seat-maintenance.index', $readyRoom))
            ->assertSee(route('admin.rooms.layout.show', $missingLayoutRoom));

        $html = $response->getContent();
        foreach (['Doanh thu', 'Tỷ lệ lấp đầy', 'ghế đã bán', 'nhân viên đang làm', 'setInterval', 'fetch(', 'WebSocket'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }

        $this->get(route('admin.rooms.show', $showingRoom))->assertOk();
        $this->get(route('admin.rooms.seat-maintenance.index', $readyRoom))->assertOk();
        $this->get(route('admin.rooms.layout.show', $missingLayoutRoom))->assertOk();
    }

    public function test_operational_sections_have_neutral_empty_states(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('admin.cinemas.show', $this->cinema))
            ->assertOk()
            ->assertSee('Hôm nay chưa có lịch chiếu.')
            ->assertSee('Hiện không có suất đang chiếu.')
            ->assertSee('Không có suất bắt đầu trong 120 phút tới.')
            ->assertSee('Chi nhánh chưa có phòng chiếu.');
    }

    private function room(string $code, string $roomTypeCode, bool $publishedLayout = true): Room
    {
        $roomType = RoomType::query()->firstOrCreate(['code' => $roomTypeCode], [
            'name' => $roomTypeCode,
            'is_active' => true,
            'sort_order' => ++$this->sequence,
        ]);
        $room = Room::query()->create([
            'cinema_id' => $this->cinema->id,
            'room_type_id' => $roomType->id,
            'room_type' => $roomTypeCode,
            'code' => $code,
            'name' => 'Room '.$code,
            'width_mm' => 8_000,
            'length_mm' => 10_000,
            'cleaning_buffer_minutes' => 15,
            'status' => 'active',
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

    private function showtime(Room $room, string $date, string $time, int $duration, string $formatCode): Showtime
    {
        $this->sequence++;
        $movie = Movie::query()->create([
            'title' => 'Operational UI Movie '.$this->sequence,
            'slug' => 'operational-ui-movie-'.$this->sequence,
            'duration' => $duration,
            'status' => 'now_showing',
        ]);
        $format = PresentationFormat::query()->firstOrCreate(['code' => 'UI_'.$formatCode], [
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
            'status' => 'active',
        ]);
    }
}
