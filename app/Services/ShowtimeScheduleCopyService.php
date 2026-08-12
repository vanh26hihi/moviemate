<?php

namespace App\Services;

use App\Domain\Showtimes\ShowtimeScheduleCopyResult;
use App\Models\Cinema;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ShowtimeScheduleCopyService
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    public function generate(
        User $user,
        string $scope,
        int $cinemaId,
        ?int $roomId,
        string $sourceDate,
        string $targetDate,
    ): ShowtimeScheduleCopyResult {
        $cinema = Cinema::query()->active()->findOrFail($cinemaId);
        $this->cinemaAccess->authorizeCinema($user, (int) $cinema->id);

        $room = null;
        if ($scope === 'room') {
            $room = Room::query()->findOrFail($roomId);
            abort_unless((int) $room->cinema_id === (int) $cinema->id, 404);
        }

        $showtimes = Showtime::query()
            ->select('showtimes.*')
            ->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
            ->with(['movie:id,title', 'room:id,cinema_id,code,name'])
            ->where('showtimes.cinema_id', $cinema->id)
            ->where('rooms.cinema_id', $cinema->id)
            ->whereDate('showtimes.show_date', $sourceDate)
            ->where('showtimes.status', 'active')
            ->when($room, fn ($query) => $query->where('showtimes.room_id', $room->id))
            ->orderBy('rooms.code')
            ->orderBy('showtimes.show_time')
            ->orderBy('showtimes.id')
            ->get();

        if ($showtimes->isEmpty()) {
            throw ValidationException::withMessages([
                'source_date' => 'Không có suất chiếu đang hoạt động để sao chép trong phạm vi đã chọn.',
            ]);
        }

        $rows = $showtimes->values()->map(fn (Showtime $showtime, int $index): array => [
            'row_key' => 'copy-'.($index + 1),
            'movie_id' => (int) $showtime->movie_id,
            'room_id' => (int) $showtime->room_id,
            'show_date' => $targetDate,
            'show_time' => substr((string) $showtime->show_time, 0, 5),
        ])->all();

        return new ShowtimeScheduleCopyResult(
            $scope,
            (int) $cinema->id,
            $sourceDate,
            $targetDate,
            $rows,
        );
    }
}
