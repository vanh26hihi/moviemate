<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const VERSION_UPDATE_TRIGGER = 'price_book_versions_prevent_immutable_update';

    public const VERSION_DELETE_TRIGGER = 'price_book_versions_prevent_immutable_delete';

    public const ADJUSTMENT_INSERT_TRIGGER = 'price_book_adjustments_prevent_immutable_insert';

    public const ADJUSTMENT_UPDATE_TRIGGER = 'price_book_adjustments_prevent_immutable_update';

    public const ADJUSTMENT_DELETE_TRIGGER = 'price_book_adjustments_prevent_immutable_delete';

    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        Schema::create('price_books', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('price_book_versions', function (Blueprint $table) use ($sqlite): void {
            $table->id();
            $table->foreignId('price_book_id')->constrained('price_books')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('version_number');
            $table->enum('status', ['draft', 'published', 'retired'])->default('draft');
            $table->rawColumn(
                'base_price_vnd',
                $sqlite
                    ? 'INTEGER NULL CHECK ("base_price_vnd" IS NULL OR "base_price_vnd" > 0)'
                    : 'BIGINT UNSIGNED NULL',
            )->nullable();
            $table->date('effective_from')->nullable();
            if ($sqlite) {
                $table->rawColumn(
                    'effective_until',
                    'DATE NULL CHECK ("effective_from" IS NULL OR "effective_until" IS NULL OR "effective_until" > "effective_from")',
                )->nullable();
            } else {
                $table->date('effective_until')->nullable();
            }
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete()->restrictOnUpdate();
            $table->timestamps();

            $table->unique(['price_book_id', 'version_number']);
            $table->index(
                ['price_book_id', 'status', 'effective_from', 'effective_until'],
                'price_book_versions_resolution_index',
            );
        });

        Schema::create('price_book_adjustments', function (Blueprint $table) use ($sqlite): void {
            $table->id();
            $table->foreignId('price_book_version_id')->constrained('price_book_versions')->cascadeOnDelete()->restrictOnUpdate();
            $table->enum('dimension', [
                'seat_type', 'room_type', 'time_window', 'weekend', 'holiday', 'cinema', 'room',
            ]);
            $table->string('label');
            $table->rawColumn(
                'amount_vnd',
                $sqlite ? 'INTEGER NOT NULL CHECK ("amount_vnd" <> 0)' : 'BIGINT NOT NULL',
            );
            $table->foreignId('seat_type_id')->nullable()->constrained('seat_types')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('room_type_id')->nullable()->constrained('room_types')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('cinema_id')->nullable()->constrained('cinemas')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->restrictOnDelete()->restrictOnUpdate();
            $table->time('time_start')->nullable();
            if ($sqlite) {
                $table->rawColumn(
                    'time_end',
                    'TIME NULL CHECK ("time_start" IS NULL OR "time_end" IS NULL OR "time_start" <> "time_end")',
                )->nullable();
            } else {
                $table->time('time_end')->nullable();
            }
            $table->date('holiday_date_from')->nullable();
            if ($sqlite) {
                $table->rawColumn(
                    'holiday_date_until',
                    'DATE NULL CHECK ("holiday_date_from" IS NULL OR "holiday_date_until" IS NULL OR "holiday_date_until" > "holiday_date_from")',
                )->nullable();
            } else {
                $table->date('holiday_date_until')->nullable();
            }
            $table->json('weekend_days')->nullable();
            $table->timestamps();

            $table->index(['price_book_version_id', 'dimension'], 'price_book_adjustments_match_index');
            $table->unique(
                ['price_book_version_id', 'dimension', 'seat_type_id'],
                'price_book_adjustments_seat_type_unique',
            );
            $table->unique(
                ['price_book_version_id', 'dimension', 'room_type_id'],
                'price_book_adjustments_room_type_unique',
            );
            $table->unique(
                ['price_book_version_id', 'dimension', 'cinema_id'],
                'price_book_adjustments_cinema_unique',
            );
            $table->unique(
                ['price_book_version_id', 'dimension', 'room_id'],
                'price_book_adjustments_room_unique',
            );
        });

        $this->installChecks();
        $this->createTriggers();
    }

    public function down(): void
    {
        $this->dropTriggers();
        Schema::dropIfExists('price_book_adjustments');
        Schema::dropIfExists('price_book_versions');
        Schema::dropIfExists('price_books');
    }

    private function installChecks(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE price_book_versions
            ADD CONSTRAINT price_book_versions_base_positive CHECK (base_price_vnd IS NULL OR base_price_vnd > 0),
            ADD CONSTRAINT price_book_versions_period_valid CHECK (
                effective_from IS NULL OR effective_until IS NULL OR effective_until > effective_from
            )');
        DB::statement('ALTER TABLE price_book_adjustments
            ADD CONSTRAINT price_book_adjustments_amount_nonzero CHECK (amount_vnd <> 0),
            ADD CONSTRAINT price_book_adjustments_time_nonzero CHECK (
                time_start IS NULL OR time_end IS NULL OR time_start <> time_end
            ),
            ADD CONSTRAINT price_book_adjustments_holiday_period_valid CHECK (
                holiday_date_from IS NULL OR holiday_date_until IS NULL OR holiday_date_until > holiday_date_from
            )');
    }

    private function createTriggers(): void
    {
        $this->dropTriggers();

        if (DB::getDriverName() === 'sqlite') {
            $this->createSqliteTriggers();

            return;
        }

        DB::unprepared('CREATE TRIGGER '.self::VERSION_UPDATE_TRIGGER.' BEFORE UPDATE ON price_book_versions
            FOR EACH ROW
            BEGIN
                IF OLD.status <> \'draft\' AND (
                    NOT (OLD.price_book_id <=> NEW.price_book_id)
                    OR NOT (OLD.version_number <=> NEW.version_number)
                    OR NOT (OLD.base_price_vnd <=> NEW.base_price_vnd)
                    OR NOT (OLD.effective_from <=> NEW.effective_from)
                    OR NOT (OLD.effective_until <=> NEW.effective_until)
                    OR NOT (OLD.published_at <=> NEW.published_at)
                    OR (OLD.status = \'retired\' AND NOT (OLD.retired_at <=> NEW.retired_at))
                    OR NEW.status = \'draft\'
                    OR (OLD.status = \'retired\' AND NEW.status <> \'retired\')
                    OR (OLD.status = \'published\' AND NEW.status NOT IN (\'published\', \'retired\'))
                ) THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Published PriceBookVersion financial definition is immutable.\';
                END IF;
            END');

        DB::unprepared('CREATE TRIGGER '.self::VERSION_DELETE_TRIGGER.' BEFORE DELETE ON price_book_versions
            FOR EACH ROW
            BEGIN
                IF OLD.status <> \'draft\' THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Published or retired PriceBookVersion cannot be deleted.\';
                END IF;
            END');

        DB::unprepared('CREATE TRIGGER '.self::ADJUSTMENT_INSERT_TRIGGER.' BEFORE INSERT ON price_book_adjustments
            FOR EACH ROW
            BEGIN
                DECLARE parent_status VARCHAR(20) DEFAULT NULL;
                SELECT status INTO parent_status FROM price_book_versions WHERE id = NEW.price_book_version_id FOR UPDATE;
                IF parent_status IS NULL OR parent_status <> \'draft\' THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'PriceBook adjustments are immutable outside draft.\';
                END IF;
            END');

        DB::unprepared('CREATE TRIGGER '.self::ADJUSTMENT_UPDATE_TRIGGER.' BEFORE UPDATE ON price_book_adjustments
            FOR EACH ROW
            BEGIN
                DECLARE old_parent_status VARCHAR(20) DEFAULT NULL;
                DECLARE new_parent_status VARCHAR(20) DEFAULT NULL;
                SELECT status INTO old_parent_status FROM price_book_versions WHERE id = OLD.price_book_version_id FOR UPDATE;
                SET new_parent_status = old_parent_status;
                IF NOT (OLD.price_book_version_id <=> NEW.price_book_version_id) THEN
                    SELECT status INTO new_parent_status FROM price_book_versions WHERE id = NEW.price_book_version_id FOR UPDATE;
                END IF;
                IF old_parent_status IS NULL OR old_parent_status <> \'draft\'
                    OR new_parent_status IS NULL OR new_parent_status <> \'draft\' THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'PriceBook adjustments are immutable outside draft.\';
                END IF;
            END');

        DB::unprepared('CREATE TRIGGER '.self::ADJUSTMENT_DELETE_TRIGGER.' BEFORE DELETE ON price_book_adjustments
            FOR EACH ROW
            BEGIN
                DECLARE parent_status VARCHAR(20) DEFAULT NULL;
                SELECT status INTO parent_status FROM price_book_versions WHERE id = OLD.price_book_version_id FOR UPDATE;
                IF parent_status IS NULL OR parent_status <> \'draft\' THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'PriceBook adjustments are immutable outside draft.\';
                END IF;
            END');
    }

    private function createSqliteTriggers(): void
    {
        DB::unprepared('CREATE TRIGGER '.self::VERSION_UPDATE_TRIGGER.' BEFORE UPDATE ON price_book_versions
            WHEN OLD.status <> \'draft\' AND (
                OLD.price_book_id IS NOT NEW.price_book_id
                OR OLD.version_number IS NOT NEW.version_number
                OR OLD.base_price_vnd IS NOT NEW.base_price_vnd
                OR OLD.effective_from IS NOT NEW.effective_from
                OR OLD.effective_until IS NOT NEW.effective_until
                OR OLD.published_at IS NOT NEW.published_at
                OR (OLD.status = \'retired\' AND OLD.retired_at IS NOT NEW.retired_at)
                OR NEW.status = \'draft\'
                OR (OLD.status = \'retired\' AND NEW.status <> \'retired\')
                OR (OLD.status = \'published\' AND NEW.status NOT IN (\'published\', \'retired\'))
            )
            BEGIN SELECT RAISE(ABORT, \'Published PriceBookVersion financial definition is immutable.\'); END');
        DB::unprepared('CREATE TRIGGER '.self::VERSION_DELETE_TRIGGER.' BEFORE DELETE ON price_book_versions
            WHEN OLD.status <> \'draft\'
            BEGIN SELECT RAISE(ABORT, \'Published or retired PriceBookVersion cannot be deleted.\'); END');
        DB::unprepared('CREATE TRIGGER '.self::ADJUSTMENT_INSERT_TRIGGER.' BEFORE INSERT ON price_book_adjustments
            WHEN COALESCE((SELECT status FROM price_book_versions WHERE id = NEW.price_book_version_id), \'\') <> \'draft\'
            BEGIN SELECT RAISE(ABORT, \'PriceBook adjustments are immutable outside draft.\'); END');
        DB::unprepared('CREATE TRIGGER '.self::ADJUSTMENT_UPDATE_TRIGGER.' BEFORE UPDATE ON price_book_adjustments
            WHEN COALESCE((SELECT status FROM price_book_versions WHERE id = OLD.price_book_version_id), \'\') <> \'draft\'
                OR COALESCE((SELECT status FROM price_book_versions WHERE id = NEW.price_book_version_id), \'\') <> \'draft\'
            BEGIN SELECT RAISE(ABORT, \'PriceBook adjustments are immutable outside draft.\'); END');
        DB::unprepared('CREATE TRIGGER '.self::ADJUSTMENT_DELETE_TRIGGER.' BEFORE DELETE ON price_book_adjustments
            WHEN COALESCE((SELECT status FROM price_book_versions WHERE id = OLD.price_book_version_id), \'\') <> \'draft\'
            BEGIN SELECT RAISE(ABORT, \'PriceBook adjustments are immutable outside draft.\'); END');
    }

    private function dropTriggers(): void
    {
        foreach ([
            self::VERSION_UPDATE_TRIGGER,
            self::VERSION_DELETE_TRIGGER,
            self::ADJUSTMENT_INSERT_TRIGGER,
            self::ADJUSTMENT_UPDATE_TRIGGER,
            self::ADJUSTMENT_DELETE_TRIGGER,
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
