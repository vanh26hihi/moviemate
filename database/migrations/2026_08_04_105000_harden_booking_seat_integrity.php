<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_LOCK = 'ACTIVE';

    private const ACTIVE_BOOKING_STATUSES = ['pending_payment', 'paid', 'used'];

    private const RELEASED_BOOKING_STATUSES = ['cancelled', 'expired'];

    private const BOOKING_IDENTITY_UNIQUE = 'bookings_id_showtime_unique';

    private const BOOKING_SEAT_PARENT_FOREIGN = 'booking_seats_booking_showtime_foreign';

    private const ACTIVE_LOCK_CHECK = 'booking_seats_active_lock_key_check';

    public function up(): void
    {
        $state = $this->hardeningState();
        $this->assertStateCanBeMigrated($state);

        $this->preflightAndNormalizeLegacyData();

        if ($this->isFullyHardened($state)) {
            return;
        }

        // All data conflicts that can be detected in advance have been checked above.
        // MySQL auto-commits DDL, so each operation below is guarded for a clear rerun.
        if (! Schema::hasColumn('bookings', 'checkout_request_fingerprint_hash')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->char('checkout_request_fingerprint_hash', 64)
                    ->nullable()
                    ->after('checkout_idempotency_key_hash');
            });
        }

        if (! Schema::hasColumn('bookings', 'guest_access_expires_at')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->timestamp('guest_access_expires_at')
                    ->nullable()
                    ->after('guest_access_token_hash');
            });
        }

        if (! $this->hasBookingIdentityUnique()) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->unique(['id', 'showtime_id'], self::BOOKING_IDENTITY_UNIQUE);
            });
        }

        if ($this->bookingSeatShowtimeIsNullable()) {
            Schema::table('booking_seats', function (Blueprint $table): void {
                $table->unsignedBigInteger('showtime_id')->nullable(false)->change();
            });
        }

        if (! $this->hasBookingSeatParentForeign()) {
            Schema::table('booking_seats', function (Blueprint $table): void {
                $table->foreign(['booking_id', 'showtime_id'], self::BOOKING_SEAT_PARENT_FOREIGN)
                    ->references(['id', 'showtime_id'])
                    ->on('bookings')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->hasActiveLockConstraint()) {
            $this->addActiveLockConstraint();
        }
    }

    public function down(): void
    {
        $state = $this->hardeningState();
        if ($this->isNotHardened($state)) {
            return;
        }
        if (! $this->isFullyHardened($state)) {
            throw new RuntimeException($this->partialStateMessage($state, 'rollback'));
        }

        $rebooked = DB::table('booking_seats')
            ->groupBy('showtime_id', 'seat_id')
            ->havingRaw('COUNT(*) > 1')
            ->select('showtime_id', 'seat_id', DB::raw('COUNT(*) as history_count'))
            ->first();

        if ($rebooked) {
            throw new RuntimeException(
                "Cannot roll back booking seat integrity: showtime {$rebooked->showtime_id}, "
                ."seat {$rebooked->seat_id} has {$rebooked->history_count} history rows. "
                .'Rollback would make released/rebook history incompatible with the legacy schema.'
            );
        }

        if (DB::table('booking_seats')->whereNull('active_lock_key')->exists()) {
            throw new RuntimeException(
                'Cannot roll back booking seat integrity while released lock history exists. '
                .'The migration will not discard history or recreate an incompatible legacy uniqueness rule.'
            );
        }

        if ($this->hasActiveLockConstraint()) {
            $this->dropActiveLockConstraint();
        }

        if ($this->hasBookingSeatParentForeign()) {
            Schema::table('booking_seats', function (Blueprint $table): void {
                if (DB::getDriverName() === 'sqlite') {
                    $table->dropForeign(['booking_id', 'showtime_id']);
                } else {
                    $table->dropForeign(self::BOOKING_SEAT_PARENT_FOREIGN);
                }
            });
        }

        if (! $this->bookingSeatShowtimeIsNullable()) {
            Schema::table('booking_seats', function (Blueprint $table): void {
                $table->unsignedBigInteger('showtime_id')->nullable()->change();
            });
        }

        if ($this->hasBookingIdentityUnique()) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropUnique(self::BOOKING_IDENTITY_UNIQUE);
            });
        }

        $bookingColumns = array_values(array_filter([
            Schema::hasColumn('bookings', 'checkout_request_fingerprint_hash')
                ? 'checkout_request_fingerprint_hash'
                : null,
            Schema::hasColumn('bookings', 'guest_access_expires_at')
                ? 'guest_access_expires_at'
                : null,
        ]));

        if ($bookingColumns !== []) {
            Schema::table('bookings', function (Blueprint $table) use ($bookingColumns): void {
                $table->dropColumn($bookingColumns);
            });
        }
    }

    private function preflightAndNormalizeLegacyData(): void
    {
        DB::transaction(function (): void {
            // Joined backfill intentionally leaves orphans NULL so the following audit can report them.
            $this->backfillShowtimes();

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
            $this->assertOnlyKnownBookingStatuses();
            $this->assertNoProspectiveDuplicateActiveLocks();
            $this->normalizeActiveLocks();
            $this->assertOnlyKnownLockValues();
            $this->assertNoDuplicateActiveLocks();

            if (DB::table('booking_seats')->whereNull('showtime_id')->exists()) {
                throw new RuntimeException('Booking seat showtime backfill left NULL values.');
            }
        });
    }

    private function assertOnlyKnownBookingStatuses(): void
    {
        $knownStatuses = array_merge(self::ACTIVE_BOOKING_STATUSES, self::RELEASED_BOOKING_STATUSES);
        $unknown = DB::table('bookings')
            ->where(function ($query) use ($knownStatuses): void {
                $query->whereNull('booking_status')
                    ->orWhereNotIn('booking_status', $knownStatuses);
            })
            ->select('id', 'booking_status')
            ->first();

        if ($unknown) {
            throw new RuntimeException(
                "Booking {$unknown->id} has unsupported booking_status '{$unknown->booking_status}'. "
                .'Verified statuses are pending_payment, paid, used, cancelled, and expired.'
            );
        }
    }

    private function normalizeActiveLocks(): void
    {
        $active = "'".implode("','", self::ACTIVE_BOOKING_STATUSES)."'";

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'UPDATE booking_seats '
                .'INNER JOIN bookings ON bookings.id = booking_seats.booking_id '
                ."SET booking_seats.active_lock_key = CASE WHEN bookings.booking_status IN ({$active}) "
                ."THEN 'ACTIVE' ELSE NULL END"
            );

            return;
        }

        DB::statement(
            'UPDATE booking_seats SET active_lock_key = CASE WHEN ('
            .'SELECT bookings.booking_status FROM bookings WHERE bookings.id = booking_seats.booking_id'
            .") IN ({$active}) THEN 'ACTIVE' ELSE NULL END"
        );
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

    private function assertNoProspectiveDuplicateActiveLocks(): void
    {
        $duplicate = DB::table('booking_seats')
            ->join('bookings', 'bookings.id', '=', 'booking_seats.booking_id')
            ->whereIn('bookings.booking_status', self::ACTIVE_BOOKING_STATUSES)
            ->groupBy('booking_seats.showtime_id', 'booking_seats.seat_id')
            ->havingRaw('COUNT(*) > 1')
            ->select(
                'booking_seats.showtime_id',
                'booking_seats.seat_id',
                DB::raw('COUNT(*) as duplicate_count'),
            )
            ->first();

        if (! $duplicate) {
            return;
        }

        $bookingIds = DB::table('booking_seats')
            ->join('bookings', 'bookings.id', '=', 'booking_seats.booking_id')
            ->where('booking_seats.showtime_id', $duplicate->showtime_id)
            ->where('booking_seats.seat_id', $duplicate->seat_id)
            ->whereIn('bookings.booking_status', self::ACTIVE_BOOKING_STATUSES)
            ->orderBy('booking_seats.booking_id')
            ->pluck('booking_seats.booking_id')
            ->implode(',');

        throw new RuntimeException(
            "Duplicate ACTIVE locks after status normalization for showtime {$duplicate->showtime_id}, "
            ."seat {$duplicate->seat_id}, bookings [{$bookingIds}]."
        );
    }

    /** @return array<string, bool> */
    private function hardeningState(): array
    {
        if (DB::connection()->pretending()) {
            // Laravel suppresses metadata SELECT results in --pretend mode. Returning
            // the known pending baseline lets the command compile every guarded DDL
            // statement without weakening inspection during a real migration.
            return [
                'checkout_request_fingerprint_hash' => false,
                'guest_access_expires_at' => false,
                self::BOOKING_IDENTITY_UNIQUE => false,
                'booking_seats_showtime_not_null' => false,
                self::BOOKING_SEAT_PARENT_FOREIGN => false,
                self::ACTIVE_LOCK_CHECK => false,
            ];
        }

        return [
            'checkout_request_fingerprint_hash' => Schema::hasColumn('bookings', 'checkout_request_fingerprint_hash'),
            'guest_access_expires_at' => Schema::hasColumn('bookings', 'guest_access_expires_at'),
            self::BOOKING_IDENTITY_UNIQUE => $this->hasBookingIdentityUnique(),
            'booking_seats_showtime_not_null' => ! $this->bookingSeatShowtimeIsNullable(),
            self::BOOKING_SEAT_PARENT_FOREIGN => $this->hasBookingSeatParentForeign(),
            self::ACTIVE_LOCK_CHECK => $this->hasActiveLockConstraint(),
        ];
    }

    /** @param array<string, bool> $state */
    private function assertStateCanBeMigrated(array $state): void
    {
        if (! $this->isNotHardened($state) && ! $this->isFullyHardened($state)) {
            throw new RuntimeException($this->partialStateMessage($state, 'migration'));
        }
    }

    /** @param array<string, bool> $state */
    private function isNotHardened(array $state): bool
    {
        return ! in_array(true, $state, true);
    }

    /** @param array<string, bool> $state */
    private function isFullyHardened(array $state): bool
    {
        return ! in_array(false, $state, true);
    }

    /** @param array<string, bool> $state */
    private function partialStateMessage(array $state, string $operation): string
    {
        $present = implode(', ', array_keys(array_filter($state)));
        $missing = implode(', ', array_keys(array_filter($state, fn (bool $value): bool => ! $value)));

        return "Cannot continue booking seat integrity {$operation}: partial DDL state detected. "
            ."Present [{$present}]; missing [{$missing}]. Manual schema repair is required; no data was deleted.";
    }

    private function bookingSeatShowtimeIsNullable(): bool
    {
        if (DB::connection()->pretending()) {
            return true;
        }

        $column = collect(Schema::getColumns('booking_seats'))->firstWhere('name', 'showtime_id');

        if (! $column) {
            throw new RuntimeException('booking_seats.showtime_id is missing; migration prerequisite is incomplete.');
        }

        return (bool) $column['nullable'];
    }

    private function hasBookingIdentityUnique(): bool
    {
        if (DB::connection()->pretending()) {
            return false;
        }

        return collect(Schema::getIndexes('bookings'))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === self::BOOKING_IDENTITY_UNIQUE
                && ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['id', 'showtime_id']
        );
    }

    private function hasBookingSeatParentForeign(): bool
    {
        if (DB::connection()->pretending()) {
            return false;
        }

        return collect(Schema::getForeignKeys('booking_seats'))->contains(
            fn (array $foreign): bool => ($foreign['columns'] ?? []) === ['booking_id', 'showtime_id']
                && ($foreign['foreign_table'] ?? null) === 'bookings'
                && ($foreign['foreign_columns'] ?? []) === ['id', 'showtime_id']
        );
    }

    private function hasActiveLockConstraint(): bool
    {
        if (DB::connection()->pretending()) {
            return false;
        }

        if (DB::getDriverName() === 'sqlite') {
            $triggers = DB::table('sqlite_master')
                ->where('type', 'trigger')
                ->whereIn('name', [self::ACTIVE_LOCK_CHECK.'_insert', self::ACTIVE_LOCK_CHECK.'_update'])
                ->count();

            return $triggers === 2;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'booking_seats')
            ->where('CONSTRAINT_NAME', self::ACTIVE_LOCK_CHECK)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->exists();
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
