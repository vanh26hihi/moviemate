<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const LAYOUT_ROOM_ID_UNIQUE = 'room_layouts_room_id_id_unique';

    public const SHOWTIME_LAYOUT_FOREIGN = 'showtimes_room_layout_id_foreign';

    public const SHOWTIME_ROOM_LAYOUT_FOREIGN = 'showtimes_room_id_room_layout_id_foreign';

    public const CELL_INSERT_TRIGGER = 'room_layout_cells_prevent_immutable_insert';

    public const CELL_UPDATE_TRIGGER = 'room_layout_cells_prevent_immutable_update';

    public const CELL_DELETE_TRIGGER = 'room_layout_cells_prevent_immutable_delete';

    public const LAYOUT_UPDATE_TRIGGER = 'room_layouts_prevent_structural_mutation';

    public function up(): void
    {
        $this->assertShowtimeLayoutHistoryIsCoherent();

        if (! $this->hasIndex('room_layouts', ['room_id', 'id'], unique: true)) {
            Schema::table('room_layouts', function (Blueprint $table): void {
                $table->unique(['room_id', 'id'], self::LAYOUT_ROOM_ID_UNIQUE);
            });
        }

        if ($this->hasForeignColumns(['room_layout_id'])) {
            Schema::table('showtimes', function (Blueprint $table): void {
                $table->dropForeign(['room_layout_id']);
            });
        }

        Schema::table('showtimes', function (Blueprint $table): void {
            $table->unsignedBigInteger('room_layout_id')->nullable(false)->change();
        });

        if (! $this->hasForeignColumns(['room_id', 'room_layout_id'])) {
            Schema::table('showtimes', function (Blueprint $table): void {
                $table->foreign(['room_id', 'room_layout_id'], self::SHOWTIME_ROOM_LAYOUT_FOREIGN)
                    ->references(['room_id', 'id'])
                    ->on('room_layouts')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();
            });
        }

        $this->createTriggers();
    }

    public function down(): void
    {
        $this->dropTriggers();

        if ($this->hasForeignColumns(['room_id', 'room_layout_id'])) {
            Schema::table('showtimes', function (Blueprint $table): void {
                $table->dropForeign(['room_id', 'room_layout_id']);
            });
        }

        Schema::table('showtimes', function (Blueprint $table): void {
            $table->unsignedBigInteger('room_layout_id')->nullable()->change();
        });

        if ($this->hasIndex('room_layouts', ['room_id', 'id'], unique: true)) {
            Schema::table('room_layouts', function (Blueprint $table): void {
                $table->dropUnique(self::LAYOUT_ROOM_ID_UNIQUE);
            });
        }

        if (! $this->hasForeignColumns(['room_layout_id'])) {
            Schema::table('showtimes', function (Blueprint $table): void {
                $table->foreign('room_layout_id', self::SHOWTIME_LAYOUT_FOREIGN)
                    ->references('id')
                    ->on('room_layouts')
                    ->restrictOnDelete()
                    ->restrictOnUpdate();
            });
        }
    }

    private function assertShowtimeLayoutHistoryIsCoherent(): void
    {
        $null = DB::table('showtimes')->whereNull('room_layout_id')->count();
        $dangling = DB::table('showtimes')
            ->leftJoin('room_layouts', 'room_layouts.id', '=', 'showtimes.room_layout_id')
            ->whereNotNull('showtimes.room_layout_id')
            ->whereNull('room_layouts.id')
            ->count();
        $mismatched = DB::table('showtimes')
            ->join('room_layouts', 'room_layouts.id', '=', 'showtimes.room_layout_id')
            ->whereColumn('showtimes.room_id', '!=', 'room_layouts.room_id')
            ->count();
        $draft = DB::table('showtimes')
            ->join('room_layouts', 'room_layouts.id', '=', 'showtimes.room_layout_id')
            ->where('room_layouts.status', 'draft')
            ->count();

        if ($null !== 0 || $dangling !== 0 || $mismatched !== 0 || $draft !== 0) {
            throw new RuntimeException(
                'Cannot harden Showtime/RoomLayout history while invalid references exist: '
                ."null_layout={$null}, dangling_layout={$dangling}, room_mismatch={$mismatched}, draft_layout={$draft}. "
                .'No historical layout was guessed or backfilled.',
            );
        }
    }

    private function createTriggers(): void
    {
        $this->dropTriggers();

        if (DB::getDriverName() === 'sqlite') {
            $this->createSqliteTriggers();

            return;
        }

        DB::unprepared('CREATE TRIGGER '.self::CELL_INSERT_TRIGGER.' BEFORE INSERT ON room_layout_cells
            FOR EACH ROW
            BEGIN
                DECLARE parent_status VARCHAR(20) DEFAULT NULL;
                SELECT status INTO parent_status FROM room_layouts WHERE id = NEW.room_layout_id FOR UPDATE;
                IF parent_status IS NULL OR parent_status <> \'draft\' THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'RoomLayout structure is immutable outside draft.\';
                END IF;
            END');

        DB::unprepared('CREATE TRIGGER '.self::CELL_UPDATE_TRIGGER.' BEFORE UPDATE ON room_layout_cells
            FOR EACH ROW
            BEGIN
                DECLARE old_parent_status VARCHAR(20) DEFAULT NULL;
                DECLARE new_parent_status VARCHAR(20) DEFAULT NULL;
                SELECT status INTO old_parent_status FROM room_layouts WHERE id = OLD.room_layout_id FOR UPDATE;
                SET new_parent_status = old_parent_status;
                IF NOT (OLD.room_layout_id <=> NEW.room_layout_id) THEN
                    SELECT status INTO new_parent_status FROM room_layouts WHERE id = NEW.room_layout_id FOR UPDATE;
                END IF;
                IF old_parent_status IS NULL OR old_parent_status <> \'draft\'
                    OR new_parent_status IS NULL OR new_parent_status <> \'draft\' THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'RoomLayout structure is immutable outside draft.\';
                END IF;
            END');

        DB::unprepared('CREATE TRIGGER '.self::CELL_DELETE_TRIGGER.' BEFORE DELETE ON room_layout_cells
            FOR EACH ROW
            BEGIN
                DECLARE parent_status VARCHAR(20) DEFAULT NULL;
                SELECT status INTO parent_status FROM room_layouts WHERE id = OLD.room_layout_id FOR UPDATE;
                IF parent_status IS NULL OR parent_status <> \'draft\' THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'RoomLayout structure is immutable outside draft.\';
                END IF;
            END');

        DB::unprepared('CREATE TRIGGER '.self::LAYOUT_UPDATE_TRIGGER.' BEFORE UPDATE ON room_layouts
            FOR EACH ROW
            BEGIN
                IF OLD.status <> \'draft\' AND (
                    NOT (OLD.room_id <=> NEW.room_id)
                    OR NOT (OLD.version <=> NEW.version)
                    OR NOT (OLD.rows <=> NEW.rows)
                    OR NOT (OLD.columns <=> NEW.columns)
                    OR NOT (OLD.screen_position <=> NEW.screen_position)
                    OR NEW.status = \'draft\'
                    OR (OLD.status = \'retired\' AND NEW.status <> \'retired\')
                ) THEN
                    SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'RoomLayout structure is immutable outside draft.\';
                END IF;
            END');
    }

    private function createSqliteTriggers(): void
    {
        DB::unprepared('CREATE TRIGGER '.self::CELL_INSERT_TRIGGER.' BEFORE INSERT ON room_layout_cells
            WHEN COALESCE((SELECT status FROM room_layouts WHERE id = NEW.room_layout_id), \'\') <> \'draft\'
            BEGIN SELECT RAISE(ABORT, \'RoomLayout structure is immutable outside draft.\'); END');
        DB::unprepared('CREATE TRIGGER '.self::CELL_UPDATE_TRIGGER.' BEFORE UPDATE ON room_layout_cells
            WHEN COALESCE((SELECT status FROM room_layouts WHERE id = OLD.room_layout_id), \'\') <> \'draft\'
                OR COALESCE((SELECT status FROM room_layouts WHERE id = NEW.room_layout_id), \'\') <> \'draft\'
            BEGIN SELECT RAISE(ABORT, \'RoomLayout structure is immutable outside draft.\'); END');
        DB::unprepared('CREATE TRIGGER '.self::CELL_DELETE_TRIGGER.' BEFORE DELETE ON room_layout_cells
            WHEN COALESCE((SELECT status FROM room_layouts WHERE id = OLD.room_layout_id), \'\') <> \'draft\'
            BEGIN SELECT RAISE(ABORT, \'RoomLayout structure is immutable outside draft.\'); END');
        DB::unprepared('CREATE TRIGGER '.self::LAYOUT_UPDATE_TRIGGER.' BEFORE UPDATE ON room_layouts
            WHEN OLD.status <> \'draft\' AND (
                OLD.room_id IS NOT NEW.room_id
                OR OLD.version IS NOT NEW.version
                OR OLD.rows IS NOT NEW.rows
                OR OLD.columns IS NOT NEW.columns
                OR OLD.screen_position IS NOT NEW.screen_position
                OR NEW.status = \'draft\'
                OR (OLD.status = \'retired\' AND NEW.status <> \'retired\')
            )
            BEGIN SELECT RAISE(ABORT, \'RoomLayout structure is immutable outside draft.\'); END');
    }

    private function dropTriggers(): void
    {
        foreach ([self::CELL_INSERT_TRIGGER, self::CELL_UPDATE_TRIGGER, self::CELL_DELETE_TRIGGER, self::LAYOUT_UPDATE_TRIGGER] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    /** @param list<string> $columns */
    private function hasIndex(string $table, array $columns, bool $unique): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => ($index['columns'] ?? []) === $columns
                && (bool) ($index['unique'] ?? false) === $unique,
        );
    }

    /** @param list<string> $columns */
    private function hasForeignColumns(array $columns): bool
    {
        return collect(Schema::getForeignKeys('showtimes'))->contains(
            fn (array $foreign): bool => ($foreign['columns'] ?? []) === $columns,
        );
    }
};
