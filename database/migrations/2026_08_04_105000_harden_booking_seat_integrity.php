<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_LOCK = 'ACTIVE';

    private const BOOKING_IDENTITY_UNIQUE = 'bookings_id_showtime_unique';

    private const BOOKING_SEAT_PARENT_FOREIGN = 'booking_seats_booking_showtime_foreign';

    private const ACTIVE_LOCK_CHECK = 'booking_seats_active_lock_key_check';

    public function up(): void
    {
        DB::transaction(function (): void {
            $orphan = DB::table('booking_seats')
                ->leftJoin('bookings', 'bookings.id', '=', 'booking_seats.booking_id')
                ->whereNull('bookings.id')
                ->select('booking_seats.id', 'booking_seats.booking_id')
                ->first();

            if ($orphan) {
                throw new RuntimeException(
                    "Booking seat {$orphan->id} references missing booking {$orphan->booking_id}."
                );
            }

            $parentWithoutShowtime = DB::table('booking_seats')
                ->join('bookings', 'bookings.id', '=', 'booking_seats.booking_id')
                ->whereNull('bookings.showtime_id')
                ->select('booking_seats.id', 'booking_seats.booking_id')
                ->first();

            if ($parentWithoutShowtime) {
                throw new RuntimeException(
                    "Booking {$parentWithoutShowtime->booking_id} has no showtime for booking seat {$parentWithoutShowtime->id}."
                );
            }

            $this->assertNoMismatchedShowtimes();
            $this->assertOnlyKnownLockValues();
            $this->backfillShowtimes();
            $this->assertNoMismatchedShowtimes();
            $this->assertNoDuplicateActiveLocks();

            if (DB::table('booking_seats')->whereNull('showtime_id')->exists()) {
                throw new RuntimeException('Booking seat showtime backfill left NULL values.');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->char('checkout_request_fingerprint_hash', 64)
                ->nullable()
                ->after('checkout_idempotency_key_hash');
            $table->timestamp('guest_access_expires_at')
                ->nullable()
                ->after('guest_access_token_hash');
            $table->unique(['id', 'showtime_id'], self::BOOKING_IDENTITY_UNIQUE);
        });

        Schema::table('booking_seats', function (Blueprint $table) {
            $table->unsignedBigInteger('showtime_id')->nullable(false)->change();
            $table->foreign(['booking_id', 'showtime_id'], self::BOOKING_SEAT_PARENT_FOREIGN)
                ->references(['id', 'showtime_id'])
                ->on('bookings')
                ->cascadeOnDelete();
        });

        $this->addActiveLockConstraint();
    }

    public function down(): void
    {
        $this->dropActiveLockConstraint();

        Schema::table('booking_seats', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['booking_id', 'showtime_id']);
            } else {
                $table->dropForeign(self::BOOKING_SEAT_PARENT_FOREIGN);
            }
            $table->unsignedBigInteger('showtime_id')->nullable()->change();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(self::BOOKING_IDENTITY_UNIQUE);
            $table->dropColumn([
                'checkout_request_fingerprint_hash',
                'guest_access_expires_at',
            ]);
        });
    }

    private function assertNoMismatchedShowtimes(): void
    {
        $mismatch = DB::table('booking_seats')
            ->join('bookings', 'bookings.id', '=', 'booking_seats.booking_id')
            ->whereNotNull('booking_seats.showtime_id')
            ->whereColumn('booking_seats.showtime_id', '!=', 'bookings.showtime_id')
            ->select(
                'booking_seats.id',
                'booking_seats.booking_id',
                'booking_seats.showtime_id as seat_showtime_id',
                'bookings.showtime_id as booking_showtime_id',
            )
            ->first();

        if ($mismatch) {
            throw new RuntimeException(
                "Booking seat {$mismatch->id} has showtime {$mismatch->seat_showtime_id}, "
                ."but booking {$mismatch->booking_id} belongs to showtime {$mismatch->booking_showtime_id}."
            );
        }
    }

    private function assertOnlyKnownLockValues(): void
    {
        $invalid = DB::table('booking_seats')
            ->whereNotNull('active_lock_key')
            ->where('active_lock_key', '!=', self::ACTIVE_LOCK)
            ->select('id', 'active_lock_key')
            ->first();

        if ($invalid) {
            throw new RuntimeException(
                "Booking seat {$invalid->id} has unsupported active_lock_key '{$invalid->active_lock_key}'."
            );
        }
    }

    private function backfillShowtimes(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'UPDATE booking_seats '
                .'INNER JOIN bookings ON bookings.id = booking_seats.booking_id '
                .'SET booking_seats.showtime_id = bookings.showtime_id '
                .'WHERE booking_seats.showtime_id IS NULL'
            );

            return;
        }

        DB::statement(
            'UPDATE booking_seats SET showtime_id = ('
            .'SELECT bookings.showtime_id FROM bookings WHERE bookings.id = booking_seats.booking_id'
            .') WHERE showtime_id IS NULL'
        );
    }

    private function assertNoDuplicateActiveLocks(): void
    {
        $duplicate = DB::table('booking_seats')
            ->where('active_lock_key', self::ACTIVE_LOCK)
            ->groupBy('showtime_id', 'seat_id')
            ->havingRaw('COUNT(*) > 1')
            ->select('showtime_id', 'seat_id', DB::raw('COUNT(*) as duplicate_count'))
            ->first();

        if (! $duplicate) {
            return;
        }

        $bookingIds = DB::table('booking_seats')
            ->where('showtime_id', $duplicate->showtime_id)
            ->where('seat_id', $duplicate->seat_id)
            ->where('active_lock_key', self::ACTIVE_LOCK)
            ->orderBy('booking_id')
            ->pluck('booking_id')
            ->implode(',');

        throw new RuntimeException(
            "Duplicate ACTIVE locks for showtime {$duplicate->showtime_id}, seat {$duplicate->seat_id}, "
            ."bookings [{$bookingIds}]."
        );
    }

    private function addActiveLockConstraint(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                'CREATE TRIGGER '.self::ACTIVE_LOCK_CHECK.'_insert '
                .'BEFORE INSERT ON booking_seats '
                ."WHEN NEW.active_lock_key IS NOT NULL AND NEW.active_lock_key <> 'ACTIVE' "
                ."BEGIN SELECT RAISE(ABORT, 'active_lock_key must be ACTIVE or NULL'); END"
            );
            DB::statement(
                'CREATE TRIGGER '.self::ACTIVE_LOCK_CHECK.'_update '
                .'BEFORE UPDATE OF active_lock_key ON booking_seats '
                ."WHEN NEW.active_lock_key IS NOT NULL AND NEW.active_lock_key <> 'ACTIVE' "
                ."BEGIN SELECT RAISE(ABORT, 'active_lock_key must be ACTIVE or NULL'); END"
            );

            return;
        }

        DB::statement(
            'ALTER TABLE booking_seats ADD CONSTRAINT '.self::ACTIVE_LOCK_CHECK
            ." CHECK (active_lock_key IS NULL OR active_lock_key = 'ACTIVE')"
        );
    }

    private function dropActiveLockConstraint(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS '.self::ACTIVE_LOCK_CHECK.'_insert');
            DB::statement('DROP TRIGGER IF EXISTS '.self::ACTIVE_LOCK_CHECK.'_update');

            return;
        }

        DB::statement('ALTER TABLE booking_seats DROP CHECK '.self::ACTIVE_LOCK_CHECK);
    }
};
