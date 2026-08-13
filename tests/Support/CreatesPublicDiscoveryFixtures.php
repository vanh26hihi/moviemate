<?php

namespace Tests\Support;

use App\Models\Cinema;
use App\Models\CinemaOperatingHour;
use App\Models\CinemaPricingRule;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\Showtime;

trait CreatesPublicDiscoveryFixtures
{
    protected function presentationFormatForDiscovery(): PresentationFormat
    {
        return PresentationFormat::query()->firstOrCreate(['code' => 'TEST_2D'], [
            'name' => 'Test 2D',
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    protected function publishRoomForDiscovery(Room $room): RoomLayout
    {
        $cinema = $room->cinema()->firstOrFail();
        $seat = Seat::query()->create([
            'room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1',
            'type' => 'normal', 'status' => 'active',
        ]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id, 'version' => 1, 'name' => 'Public test layout',
            'rows' => 1, 'columns' => 1, 'status' => 'draft',
        ]);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id, 'x_position' => 1, 'y_position' => 1,
            'cell_type' => 'seat', 'seat_id' => $seat->id,
        ]);
        $layout->update(['status' => 'published', 'published_at' => now()]);
        foreach (range(1, 7) as $day) {
            CinemaOperatingHour::query()->updateOrCreate(
                ['cinema_id' => $cinema->id, 'day_of_week' => $day],
                ['opens_at' => '08:00:00', 'latest_show_start_at' => '23:00:00', 'is_closed' => false],
            );
        }
        CinemaPricingRule::query()->updateOrCreate(
            ['name' => 'Public test base', 'cinema_id' => $cinema->id],
            ['rule_type' => 'base', 'amount_vnd' => 90000, 'priority' => 1000, 'status' => 'active'],
        );
        $room->presentationCapabilities()->syncWithoutDetaching($this->presentationFormatForDiscovery());

        return $layout;
    }

    protected function publicScenario(string $code, string $name, string $date, array $attributes = []): array
    {
        $cinema = Cinema::factory()->create([
            'code' => $code, 'name' => $name, 'address' => "12 {$name} Street",
            'city' => $attributes['city'] ?? 'Hà Nội', 'district' => $attributes['district'] ?? 'Cầu Giấy',
            'latitude' => $attributes['latitude'] ?? '21.0300000', 'longitude' => $attributes['longitude'] ?? '105.7800000',
            'timezone' => 'Asia/Ho_Chi_Minh', 'status' => $attributes['cinema_status'] ?? 'active',
            'archived_at' => ($attributes['cinema_status'] ?? 'active') === 'active' ? null : now(),
        ]);
        $room = Room::query()->create([
            'cinema_id' => $cinema->id, 'code' => $code.'-01', 'name' => 'Phòng '.$code,
            'room_type' => $attributes['room_type'] ?? '2D', 'width_mm' => 8_000, 'length_mm' => 10_000,
            'status' => $attributes['room_status'] ?? 'active',
        ]);
        $movie = $attributes['movie'] ?? Movie::query()->create([
            'title' => 'Movie '.$code, 'slug' => 'movie-'.strtolower($code).'-'.str()->lower(str()->random(6)),
            'duration' => 100, 'status' => $attributes['movie_status'] ?? 'now_showing',
        ]);
        $format = $this->presentationFormatForDiscovery();
        $movie->supportedPresentationFormats()->syncWithoutDetaching($format);
        $room->presentationCapabilities()->syncWithoutDetaching($format);
        $seat = Seat::query()->create([
            'room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1',
            'type' => 'normal', 'status' => 'active',
        ]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id, 'version' => 1, 'name' => 'Public layout',
            'rows' => 1, 'columns' => 1, 'status' => 'draft',
        ]);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id, 'x_position' => 1, 'y_position' => 1,
            'cell_type' => 'seat', 'seat_id' => $seat->id,
        ]);
        $layout->update([
            'status' => $attributes['layout_status'] ?? 'published',
            'published_at' => ($attributes['layout_status'] ?? 'published') === 'published' ? now() : null,
        ]);
        foreach (range(1, 7) as $day) {
            CinemaOperatingHour::query()->create([
                'cinema_id' => $cinema->id, 'day_of_week' => $day,
                'opens_at' => '08:00:00', 'latest_show_start_at' => '23:00:00', 'is_closed' => false,
            ]);
        }
        if (($attributes['with_pricing'] ?? true) && $cinema->status === 'active') {
            foreach ([['base', null, 80_000], ['seat_type', 'vip', 30_000], ['seat_type', 'couple', 80_000]] as [$type, $seatType, $amount]) {
                CinemaPricingRule::query()->create([
                    'name' => "{$code} {$type} {$seatType}", 'rule_type' => $type, 'cinema_id' => $cinema->id,
                    'seat_type' => $seatType, 'amount_vnd' => $amount, 'priority' => 100, 'status' => 'active',
                ]);
            }
        }
        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id, 'cinema_id' => $cinema->id, 'room_id' => $room->id,
            'room_layout_id' => $layout->id, 'presentation_format_id' => $format->id,
            'show_date' => $date, 'show_time' => $attributes['show_time'] ?? '19:00:00',
            'price' => 1, 'vip_price' => 2, 'pricing_version' => 'cinema-pricing-v1',
            'status' => $attributes['showtime_status'] ?? 'active',
        ]);

        return compact('cinema', 'room', 'movie', 'seat', 'layout', 'showtime');
    }
}
