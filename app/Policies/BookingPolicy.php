<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Services\CinemaAccessService;

class BookingPolicy
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    public function view(User $user, Booking $booking): bool
    {
        if ($booking->user_id === $user->id) {
            return true;
        }

        return $user->hasPermission('bookings.view')
            && $booking->cinema_id !== null
            && $this->cinemaAccess->allowsInCurrentContext($user, (int) $booking->cinema_id);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }
}
