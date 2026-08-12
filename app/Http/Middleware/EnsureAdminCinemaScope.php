<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Models\Cinema;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminCinemaScope
{
    public function __construct(private readonly CinemaAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 403);
        $this->access->resolve($user);

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && ($cinemaId = $this->cinemaId($parameter)) !== null) {
                $this->access->authorizeCinema($user, $cinemaId);
            }
        }

        return $next($request);
    }

    private function cinemaId(Model $model): ?int
    {
        return match (true) {
            $model instanceof Cinema => (int) $model->id,
            $model instanceof Room, $model instanceof Showtime => (int) $model->cinema_id,
            $model instanceof Booking => (int) ($model->cinema_id ?: $model->showtime?->cinema_id),
            $model instanceof Payment => (int) ($model->booking?->cinema_id ?: $model->booking?->showtime?->cinema_id),
            $model instanceof BookingTicketDelivery => (int) ($model->booking?->cinema_id ?: $model->booking?->showtime?->cinema_id),
            $model instanceof Order => (int) ($model->booking?->cinema_id ?: $model->pickup_cinema_id),
            $model instanceof Seat => (int) $model->room?->cinema_id,
            default => null,
        } ?: null;
    }
}
