<?php

namespace Tests\Feature\Admin;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Permission;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class Branch360FinalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        $this->cinema = Cinema::query()->active()->primary()->firstOrFail();
        $this->cinema->update([
            'name' => 'MovieMate '.str_repeat('Chi nhánh trung tâm ', 8),
            'address' => str_repeat('Địa chỉ vận hành rất dài ', 10),
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:00:00', $this->cinema->timezone)->utc());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_branch_workspace_has_final_order_accessible_landmarks_responsive_guards_and_no_polling(): void
    {
        $manager = $this->userWithRole('manager');

        $response = $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->get(route('admin.cinemas.show', $this->cinema))
            ->assertOk()
            ->assertSeeInOrder([
                'Branch 360',
                'Cần xử lý',
                'Vận hành hôm nay',
                'Đang diễn ra',
                'Sắp tới 120 phút',
                'Vận hành phòng',
                'Vận hành quầy',
                'Tài chính hôm nay',
                'Thông tin chi nhánh',
                'Phòng chiếu',
                'Manager và Staff',
                'Giờ hoạt động',
            ])
            ->assertSee('aria-labelledby="branch-action-queue-title"', false)
            ->assertSee('aria-labelledby="today-operations-title"', false)
            ->assertSee('aria-labelledby="playing-now-title"', false)
            ->assertSee('aria-labelledby="upcoming-soon-title"', false)
            ->assertSee('aria-labelledby="room-operations-title"', false)
            ->assertSee('aria-labelledby="counter-operations-title"', false)
            ->assertSee('aria-labelledby="branch-finance-title"', false)
            ->assertSee('aria-labelledby="branch-information-title"', false)
            ->assertSee('class="admin-page-title break-words"', false)
            ->assertSee('class="admin-btn-primary w-full sm:w-auto"', false)
            ->assertSee('p-4 sm:p-6', false)
            ->assertSee('tabular-nums', false)
            ->assertSee(route('admin.rooms.create'))
            ->assertSee(route('admin.cinemas.operating-hours.update', $this->cinema));

        $html = $response->getContent();
        foreach (['setInterval', 'fetch(', 'WebSocket', 'attendance', 'check-in', 'loyalty', 'Tỷ lệ lấp đầy'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }

    public function test_showtime_context_links_are_filtered_and_follow_showtime_view_authorization(): void
    {
        $manager = $this->userWithRole('manager');
        $showtime = $this->showtime();
        $url = route('admin.showtimes.index', ['show_date' => '2026-08-13']);
        $session = [CinemaAccessService::SESSION_KEY => $this->cinema->id];

        $this->actingAs($manager)->withSession($session)
            ->get(route('admin.cinemas.show', $this->cinema))
            ->assertOk()
            ->assertSee($showtime->movie->title)
            ->assertSee($url);
        $this->actingAs($manager)->withSession($session)->get($url)->assertOk();

        $manager->role->permissions()->detach(Permission::query()->where('slug', 'showtimes.view')->value('id'));
        $manager->unsetRelation('role');

        $this->actingAs($manager)->withSession($session)
            ->get(route('admin.cinemas.show', $this->cinema))
            ->assertOk()
            ->assertSee($showtime->movie->title)
            ->assertDontSee($url);
        $this->actingAs($manager)->withSession($session)->get($url)->assertForbidden();
    }

    private function showtime(): Showtime
    {
        $room = Room::query()->create([
            'cinema_id' => $this->cinema->id,
            'code' => 'LONG-ROOM-CODE-01',
            'name' => str_repeat('Phòng trình chiếu tên dài ', 6),
            'room_type' => '2D',
            'width_mm' => 8_000,
            'length_mm' => 10_000,
            'status' => 'active',
        ]);
        $movie = Movie::query()->create([
            'title' => str_repeat('Phim có tựa đề rất dài ', 8),
            'slug' => 'phase-5f-long-movie-title',
            'duration' => 90,
            'status' => 'now_showing',
        ]);

        return Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $this->cinema->id,
            'room_id' => $room->id,
            'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id,
            'show_date' => '2026-08-13',
            'show_time' => '10:30:00',
            'price' => 50_000,
            'status' => 'active',
        ])->load('movie');
    }
}
