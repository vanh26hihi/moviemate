<?php

namespace Tests\Feature\Formats;

use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

final class CustomerFormatPresentationTest extends TestCase
{
    use CreatesPublicDiscoveryFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_movie_detail_keeps_presentation_format_and_room_type_independent_for_all_combinations(): void
    {
        $twoD = $this->format('2D', 10);
        $threeD = $this->format('3D', 20);
        $cases = [
            ['MATRIX-A', 'STANDARD', $twoD],
            ['MATRIX-B', 'STANDARD', $threeD],
            ['MATRIX-C', 'IMAX', $twoD],
            ['MATRIX-D', 'IMAX', $threeD],
        ];

        foreach ($cases as [$code, $roomType, $format]) {
            $scenario = $this->publicScenario($code, 'Matrix '.$code, '2030-06-02', ['room_type' => $roomType]);
            $this->assignFormat($scenario, $format);

            $this->get(route('user.movies.show', ['slug' => $scenario['movie']->slug, 'date' => '2030-06-02']))
                ->assertOk()
                ->assertSee('Định dạng: '.$format->name)
                ->assertSee('Loại phòng: '.$roomType)
                ->assertDontSee($scenario['room']->name);
        }
    }

    public function test_cinema_formats_come_only_from_sellable_showtimes_and_use_stable_master_order(): void
    {
        $twoD = $this->format('2D', 10);
        $threeD = $this->format('3D', 20);
        $scenario = $this->publicScenario('FORMAT-SELL', 'Sellable Format Cinema', '2030-06-02', ['room_type' => 'IMAX']);
        $this->assignFormat($scenario, $twoD);
        $scenario['movie']->supportedPresentationFormats()->syncWithoutDetaching($threeD);
        $scenario['room']->presentationCapabilities()->syncWithoutDetaching($threeD);

        $response = $this->get(route('cinemas.show', ['cinema' => $scenario['cinema']->code, 'date' => '2030-06-02']))
            ->assertOk()
            ->assertSee('Định dạng suất chiếu:')
            ->assertSee('<strong class="app-text">2D</strong>', false)
            ->assertDontSee('Định dạng: 3D');

        $room = Room::query()->create([
            'cinema_id' => $scenario['cinema']->id,
            'code' => 'FORMAT-SELL-02',
            'name' => 'Physical room must stay hidden',
            'room_type' => 'STANDARD',
            'width_mm' => 8_000,
            'length_mm' => 10_000,
            'status' => 'active',
        ]);
        $layout = $this->publishRoomForDiscovery($room);
        $room->presentationCapabilities()->syncWithoutDetaching($threeD);
        $threeDShowtime = Showtime::query()->create([
            'movie_id' => $scenario['movie']->id,
            'cinema_id' => $scenario['cinema']->id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $threeD->id,
            'show_date' => '2030-06-02',
            'show_time' => '21:30:00',
            'status' => 'active',
        ]);
        $this->snapshotShowtime($threeDShowtime);

        $response = $this->get(route('cinemas.show', ['cinema' => $scenario['cinema']->code, 'date' => '2030-06-02']))
            ->assertOk()
            ->assertSee('<strong class="app-text">2D, 3D</strong>', false)
            ->assertSee('Định dạng: 3D')
            ->assertSee('Loại phòng: STANDARD')
            ->assertDontSee($room->name);

        $this->assertStringNotContainsString('3D, 2D', $response->getContent());
    }

    public function test_cutoff_and_cancelled_showtimes_do_not_contribute_phantom_formats(): void
    {
        $twoD = $this->format('2D', 10);
        $threeD = $this->format('3D', 20);
        $scenario = $this->publicScenario('FORMAT-CUTOFF', 'Cutoff Format Cinema', '2030-06-01', [
            'room_type' => 'STANDARD',
            'show_time' => '19:00:00',
        ]);
        $this->assignFormat($scenario, $twoD);
        $scenario['movie']->supportedPresentationFormats()->syncWithoutDetaching($threeD);

        foreach ([['09:00:00', 'active'], ['21:30:00', 'cancelled']] as $index => [$time, $status]) {
            $room = Room::query()->create([
                'cinema_id' => $scenario['cinema']->id,
                'code' => 'FORMAT-HIDDEN-'.$index,
                'name' => 'Hidden format room '.$index,
                'room_type' => 'IMAX',
                'width_mm' => 8_000,
                'length_mm' => 10_000,
                'status' => 'active',
            ]);
            $layout = $this->publishRoomForDiscovery($room);
            $room->presentationCapabilities()->syncWithoutDetaching($threeD);
            Showtime::query()->create([
                'movie_id' => $scenario['movie']->id,
                'cinema_id' => $scenario['cinema']->id,
                'room_id' => $room->id,
                'room_layout_id' => $layout->id,
                'presentation_format_id' => $threeD->id,
                'show_date' => '2030-06-01',
                'show_time' => $time,
                'status' => $status,
            ]);
        }

        $this->get(route('cinemas.show', ['cinema' => $scenario['cinema']->code, 'date' => '2030-06-01']))
            ->assertOk()
            ->assertSee('<strong class="app-text">2D</strong>', false)
            ->assertDontSee('Định dạng: 3D')
            ->assertDontSee('2D, 3D');
    }

    private function format(string $code, int $sortOrder): PresentationFormat
    {
        return PresentationFormat::query()->firstOrCreate(['code' => $code], [
            'name' => $code,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    private function assignFormat(array $scenario, PresentationFormat $format): void
    {
        $scenario['movie']->supportedPresentationFormats()->syncWithoutDetaching($format);
        $scenario['room']->presentationCapabilities()->syncWithoutDetaching($format);
        $scenario['showtime']->update(['presentation_format_id' => $format->id]);
    }
}
