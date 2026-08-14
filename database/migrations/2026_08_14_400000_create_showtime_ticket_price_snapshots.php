<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UPDATE_TRIGGER = 'showtime_ticket_prices_prevent_update';

    private const INSERT_TRIGGER = 'showtime_ticket_prices_validate_insert';

    private const DELETE_TRIGGER = 'showtime_ticket_prices_protect_history';

    private const BOOKING_INSERT_TRIGGER = 'booking_seats_validate_showtime_ticket_price_insert';

    private const BOOKING_UPDATE_TRIGGER = 'booking_seats_validate_showtime_ticket_price_update';

    private const ACTIVE_LOCK_INSERT_TRIGGER = 'booking_seats_active_lock_key_insert_check';

    private const ACTIVE_LOCK_UPDATE_TRIGGER = 'booking_seats_active_lock_key_update_check';

    public function up(): void
    {
        if (Schema::hasTable('showtimes') && DB::table('showtimes')->exists()) {
            throw new RuntimeException(
                'Phase 7C cannot truthfully infer immutable historical Showtime prices. Run php artisan migrate:fresh --seed.',
            );
        }

        $sqlite = DB::getDriverName() === 'sqlite';
        Schema::create('showtime_ticket_prices', function (Blueprint $table) use ($sqlite): void {
            $table->id();
            $table->foreignId('showtime_id')->constrained('showtimes')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreignId('seat_type_id')->constrained('seat_types')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('price_book_version_id')->constrained('price_book_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->rawColumn(
                'base_price_vnd',
                $sqlite
                    ? 'INTEGER NOT NULL CHECK ("base_price_vnd" > 0)'
                    : 'BIGINT UNSIGNED NOT NULL',
            );
            $table->bigInteger('adjustment_total_vnd');
            $table->rawColumn(
                'final_unit_amount_vnd',
                $sqlite
                    ? 'INTEGER NOT NULL CHECK ("final_unit_amount_vnd" > 0 AND "base_price_vnd" + "adjustment_total_vnd" = "final_unit_amount_vnd")'
                    : 'BIGINT UNSIGNED NOT NULL',
            );
            $table->json('breakdown_json');
            $table->char('pricing_fingerprint', 64);
            $table->timestamps();

            $table->unique(['showtime_id', 'seat_type_id'], 'showtime_ticket_prices_logical_unit_unique');
            $table->index('price_book_version_id', 'showtime_ticket_prices_version_index');
        });

        if (! $sqlite) {
            DB::statement('ALTER TABLE showtime_ticket_prices
                ADD CONSTRAINT showtime_ticket_prices_amounts_valid CHECK (
                    base_price_vnd > 0
                    AND final_unit_amount_vnd > 0
                    AND base_price_vnd + adjustment_total_vnd = final_unit_amount_vnd
                )');
        }

        Schema::table('booking_seats', function (Blueprint $table): void {
            $table->foreignId('showtime_ticket_price_id')
                ->nullable()
                ->after('seat_id')
                ->constrained('showtime_ticket_prices')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });

        $this->createTriggers();

        Schema::dropIfExists('cinema_pricing_rules');
        Schema::table('showtimes', fn (Blueprint $table) => $table->dropColumn(['price', 'vip_price', 'pricing_version']));
        Schema::table('seat_types', fn (Blueprint $table) => $table->dropColumn('price_modifier'));
    }

    public function down(): void
    {
        Schema::table('seat_types', function (Blueprint $table): void {
            $table->decimal('price_modifier', 10, 2)->default(0);
        });
        Schema::table('showtimes', function (Blueprint $table): void {
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('vip_price', 10, 2)->nullable();
            $table->string('pricing_version', 32)->nullable();
        });
        Schema::create('cinema_pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('rule_type', 32);
            $table->foreignId('cinema_id')->nullable()->constrained('cinemas')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->string('seat_type', 20)->nullable();
            $table->string('room_type', 20)->nullable();
            $table->json('days_of_week')->nullable();
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->bigInteger('amount_vnd');
            $table->integer('priority')->default(0);
            $table->boolean('stacks_with_weekend')->default(false);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'rule_type', 'cinema_id', 'room_id'], 'pricing_rule_match_index');
            $table->index(['starts_at', 'ends_at'], 'pricing_rule_effective_index');
            $table->index(['date_start', 'date_end'], 'pricing_rule_date_index');
        });

        $this->dropTriggers();
        Schema::table('booking_seats', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('showtime_ticket_price_id');
        });
        Schema::dropIfExists('showtime_ticket_prices');
    }

    private function createTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('CREATE TRIGGER '.self::UPDATE_TRIGGER.' BEFORE UPDATE ON showtime_ticket_prices
                BEGIN SELECT RAISE(ABORT, "Showtime ticket price snapshots are immutable"); END');
            DB::unprepared('CREATE TRIGGER '.self::INSERT_TRIGGER.' BEFORE INSERT ON showtime_ticket_prices
                BEGIN
                    SELECT CASE WHEN EXISTS (
                        SELECT 1 FROM showtime_ticket_prices
                        WHERE showtime_id = NEW.showtime_id
                          AND price_book_version_id <> NEW.price_book_version_id
                    ) THEN RAISE(ABORT, "All Showtime ticket prices must use one PriceBookVersion") END;
                END');
            DB::unprepared('CREATE TRIGGER '.self::BOOKING_INSERT_TRIGGER.' BEFORE INSERT ON booking_seats
                BEGIN
                    SELECT CASE WHEN NEW.showtime_ticket_price_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM showtime_ticket_prices stp
                        JOIN seats s ON s.id = NEW.seat_id
                        WHERE stp.id = NEW.showtime_ticket_price_id
                          AND stp.showtime_id = NEW.showtime_id
                          AND stp.seat_type_id = s.seat_type_id
                    ) THEN RAISE(ABORT, "BookingSeat price source must match its Showtime and logical SeatType") END;
                END');
            DB::unprepared('CREATE TRIGGER '.self::BOOKING_UPDATE_TRIGGER.' BEFORE UPDATE OF showtime_ticket_price_id, showtime_id, seat_id ON booking_seats
                BEGIN
                    SELECT CASE WHEN NEW.showtime_ticket_price_id IS NOT OLD.showtime_ticket_price_id
                        THEN RAISE(ABORT, "BookingSeat price source is immutable") END;
                    SELECT CASE WHEN NEW.showtime_ticket_price_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM showtime_ticket_prices stp
                        WHERE stp.id = NEW.showtime_ticket_price_id
                          AND stp.showtime_id = NEW.showtime_id
                    ) THEN RAISE(ABORT, "BookingSeat price source must remain within its Showtime") END;
                END');
            DB::unprepared('CREATE TRIGGER '.self::ACTIVE_LOCK_INSERT_TRIGGER.' BEFORE INSERT ON booking_seats
                WHEN NEW.active_lock_key IS NOT NULL AND NEW.active_lock_key <> "ACTIVE"
                BEGIN SELECT RAISE(ABORT, "active_lock_key must be ACTIVE or NULL"); END');
            DB::unprepared('CREATE TRIGGER '.self::ACTIVE_LOCK_UPDATE_TRIGGER.' BEFORE UPDATE OF active_lock_key ON booking_seats
                WHEN NEW.active_lock_key IS NOT NULL AND NEW.active_lock_key <> "ACTIVE"
                BEGIN SELECT RAISE(ABORT, "active_lock_key must be ACTIVE or NULL"); END');

            return;
        }

        DB::unprepared('CREATE TRIGGER '.self::UPDATE_TRIGGER.' BEFORE UPDATE ON showtime_ticket_prices
            FOR EACH ROW SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Showtime ticket price snapshots are immutable"');
        DB::unprepared('CREATE TRIGGER '.self::INSERT_TRIGGER.' BEFORE INSERT ON showtime_ticket_prices
            FOR EACH ROW BEGIN
                IF EXISTS (SELECT 1 FROM showtime_ticket_prices WHERE showtime_id = NEW.showtime_id AND price_book_version_id <> NEW.price_book_version_id) THEN
                    SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "All Showtime ticket prices must use one PriceBookVersion";
                END IF;
                IF EXISTS (SELECT 1 FROM booking_seats WHERE showtime_id = NEW.showtime_id) THEN
                    SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Cannot replace Showtime prices after booking history exists";
                END IF;
            END');
        DB::unprepared('CREATE TRIGGER '.self::DELETE_TRIGGER.' BEFORE DELETE ON showtime_ticket_prices
            FOR EACH ROW BEGIN
                IF EXISTS (SELECT 1 FROM booking_seats WHERE showtime_id = OLD.showtime_id) THEN
                    SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Cannot delete Showtime prices after booking history exists";
                END IF;
            END');
        DB::unprepared('CREATE TRIGGER '.self::BOOKING_INSERT_TRIGGER.' BEFORE INSERT ON booking_seats
            FOR EACH ROW BEGIN
                IF NEW.showtime_ticket_price_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM showtime_ticket_prices stp
                    JOIN seats s ON s.id = NEW.seat_id
                    WHERE stp.id = NEW.showtime_ticket_price_id
                      AND stp.showtime_id = NEW.showtime_id
                      AND stp.seat_type_id = s.seat_type_id
                ) THEN
                    SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "BookingSeat price source must match its Showtime and logical SeatType";
                END IF;
            END');
        DB::unprepared('CREATE TRIGGER '.self::BOOKING_UPDATE_TRIGGER.' BEFORE UPDATE ON booking_seats
            FOR EACH ROW BEGIN
                IF NOT (NEW.showtime_ticket_price_id <=> OLD.showtime_ticket_price_id) THEN
                    SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "BookingSeat price source is immutable";
                END IF;
                IF NEW.showtime_ticket_price_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM showtime_ticket_prices stp
                    WHERE stp.id = NEW.showtime_ticket_price_id
                      AND stp.showtime_id = NEW.showtime_id
                ) THEN
                    SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "BookingSeat price source must remain within its Showtime";
                END IF;
            END');
    }

    private function dropTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::UPDATE_TRIGGER);
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::INSERT_TRIGGER);
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::DELETE_TRIGGER);
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::BOOKING_INSERT_TRIGGER);
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::BOOKING_UPDATE_TRIGGER);
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::ACTIVE_LOCK_INSERT_TRIGGER);
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::ACTIVE_LOCK_UPDATE_TRIGGER);
    }
};
