<?php

namespace Tests\Support;

use App\Models\Cinema;
use App\Models\CinemaOperatingHour;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\RoomType;
use App\Models\Seat;
use App\Models\Showtime;

trait CreatesPublicDiscoveryFixtures
{
    use CreatesPriceBookFixtures;

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
        if (! $room->room_type_id) {
            $roomTypeCode = $room->room_type ?: '2D';
            $roomType = RoomType::query()->firstOrCreate(['code' => $roomTypeCode], [
                'name' => $roomTypeCode, 'slug' => strtolower($roomTypeCode),
                'is_active' => true, 'status' => true, 'sort_order' => 1,
            ]);
            $room->forceFill(['room_type' => $roomTypeCode, 'room_type_id' => $roomType->id])->save();
        }
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
        $this->assignLogicalSeatType($seat);
        foreach (range(1, 7) as $day) {
            CinemaOperatingHour::query()->updateOrCreate(
                ['cinema_id' => $cinema->id, 'day_of_week' => $day],
                ['opens_at' => '08:00:00', 'latest_show_start_at' => '23:00:00', 'is_closed' => false],
            );
        }
        $this->ensurePublishedPriceBook(90_000);
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
        $roomTypeCode = $attributes['room_type'] ?? '2D';
        $roomType = RoomType::query()->firstOrCreate(['code' => $roomTypeCode], [
            'name' => $roomTypeCode, 'slug' => strtolower($roomTypeCode),
            'is_active' => true, 'status' => true, 'sort_order' => 1,
        ]);
        $room = Room::query()->create([
            'cinema_id' => $cinema->id, 'code' => $code.'-01', 'name' => 'Phòng '.$code,
            'room_type' => $roomTypeCode, 'room_type_id' => $roomType->id,
            'width_mm' => 8_000, 'length_mm' => 10_000,
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
        $this->assignLogicalSeatType($seat);
        foreach (range(1, 7) as $day) {
            CinemaOperatingHour::query()->create([
                'cinema_id' => $cinema->id, 'day_of_week' => $day,
                'opens_at' => '08:00:00', 'latest_show_start_at' => '23:00:00', 'is_closed' => false,
            ]);
        }
        $withPricing = ($attributes['with_pricing'] ?? true) && $cinema->status === 'active';
        if ($withPricing) {
            $this->ensurePublishedPriceBook(80_000);
        }
        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id, 'cinema_id' => $cinema->id, 'room_id' => $room->id,
            'room_layout_id' => $layout->id, 'presentation_format_id' => $format->id,
            'show_date' => $date, 'show_time' => $attributes['show_time'] ?? '19:00:00',
            'status' => $attributes['showtime_status'] ?? 'active',
        ]);
        if ($withPricing && $layout->status === 'published') {
            $this->snapshotShowtime($showtime);
        }

        return compact('cinema', 'room', 'movie', 'seat', 'layout', 'showtime');
    }
}
