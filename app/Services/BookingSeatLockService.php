<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSeat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class BookingSeatLockService
{
    public function acquire(Booking $booking, Collection $seats, array $priceSnapshots): Collection
    {
        return DB::transaction(function () use ($booking, $seats, $priceSnapshots): Collection {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());

            if (! in_array($lockedBooking->booking_status, ['pending_payment', 'paid'], true)) {
                throw new LogicException('Chỉ có thể giữ ghế cho đơn đặt vé đang hoạt động.');
            }

            return $seats->sortBy('id')->map(function ($seat) use ($lockedBooking, $priceSnapshots) {
                if (! array_key_exists($seat->id, $priceSnapshots)) {
                    throw new LogicException('Thiếu giá ghế tại thời điểm đặt vé.');
                }

                return BookingSeat::query()->create([
                    'booking_id' => $lockedBooking->id,
                    'showtime_id' => $lockedBooking->showtime_id,
                    'seat_id' => $seat->id,
                    'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
                    'price' => $priceSnapshots[$seat->id],
                ]);
            })->values();
        });
    }

    public function release(Booking $booking): int
    {
        return DB::transaction(function () use ($booking): int {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());

            if (! in_array($lockedBooking->booking_status, ['cancelled', 'expired'], true)) {
                return 0;
            }

            return BookingSeat::query()
                ->where('booking_id', $lockedBooking->id)
                ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
                ->update(['active_lock_key' => null]);
        });
    }
}
