<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingExpirationService;
use Illuminate\Console\Command;
use Throwable;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending
        {--batch= : Number of candidate bookings to read per batch}';

    protected $description = 'Expire overdue pending bookings and release their active seat locks';

    public function handle(BookingExpirationService $expiration): int
    {
        $batchSize = (int) ($this->option('batch') ?: config('booking.expiration_batch_size', 100));
        if ($batchSize < 1 || $batchSize > 1000) {
            $this->error('The --batch option must be between 1 and 1000.');

            return self::INVALID;
        }

        $counts = ['checked' => 0, 'expired' => 0, 'skipped' => 0, 'errors' => 0];

        Booking::query()
            ->where('booking_status', 'pending_payment')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->select('id')
            ->chunkById($batchSize, function ($bookings) use ($expiration, &$counts): void {
                foreach ($bookings as $booking) {
                    $counts['checked']++;

                    try {
                        if ($expiration->expire($booking->id)) {
                            $counts['expired']++;
                        } else {
                            $counts['skipped']++;
                        }
                    } catch (Throwable $exception) {
                        $counts['errors']++;
                        report($exception);
                    }
                }
            });

        $this->table(['Checked', 'Expired', 'Skipped', 'Errors'], [[
            $counts['checked'],
            $counts['expired'],
            $counts['skipped'],
            $counts['errors'],
        ]]);

        return $counts['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
