<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovieLifecycleService
{
    private const TRANSITIONS = [
        Movie::STATUS_DRAFT => [Movie::STATUS_COMING_SOON, Movie::STATUS_INACTIVE],
        Movie::STATUS_COMING_SOON => [Movie::STATUS_NOW_SHOWING, Movie::STATUS_INACTIVE],
        Movie::STATUS_NOW_SHOWING => [Movie::STATUS_INACTIVE],
        Movie::STATUS_INACTIVE => [Movie::STATUS_COMING_SOON, Movie::STATUS_NOW_SHOWING, Movie::STATUS_ARCHIVED],
        Movie::STATUS_ARCHIVED => [],
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function transition(Movie $movie, string $status, User $actor): Movie
    {
        abort_unless($actor->hasPermission('movies.lifecycle'), 403);
        $allowed = self::TRANSITIONS[$movie->status] ?? [];
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Không thể chuyển phim từ {$movie->status_label} sang trạng thái đã chọn.",
            ]);
        }

        return DB::transaction(function () use ($movie, $status): Movie {
            $locked = Movie::query()->whereKey($movie->id)->lockForUpdate()->firstOrFail();
            if (! in_array($status, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Trạng thái phim vừa thay đổi. Hãy tải lại trang.']);
            }
            $before = $locked->status;
            $locked->update(['status' => $status]);
            $this->activityLogger->log(
                $status === Movie::STATUS_ARCHIVED ? 'movie.archived' : 'movie.status_changed',
                $locked, ['status' => $before], ['status' => $status],
            );

            return $locked->fresh();
        });
    }

    public function allowedTransitions(Movie $movie): array
    {
        return self::TRANSITIONS[$movie->status] ?? [];
    }
}
