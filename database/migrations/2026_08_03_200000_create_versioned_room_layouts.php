<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SHOWTIME_LAYOUT_FOREIGN = 'showtimes_room_layout_id_foreign';

    private const SHOWTIME_ROOM_FOREIGN = 'showtimes_room_id_foreign';

    private const SHOWTIME_LAYOUT_INDEX = 'showtimes_room_id_room_layout_id_index';

    public function up(): void
    {
        if (! Schema::hasTable('room_layouts')) {
            Schema::create('room_layouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->string('name')->nullable();
                $table->unsignedTinyInteger('rows');
                $table->unsignedTinyInteger('columns');
                $table->enum('screen_position', ['top', 'bottom'])->default('top');
                $table->enum('status', ['draft', 'published', 'retired'])->default('draft');
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['room_id', 'version']);
                $table->index(['room_id', 'status']);
            });
        }

        if (! Schema::hasTable('room_layout_cells')) {
            Schema::create('room_layout_cells', function (Blueprint $table) {
                $table->id();
                $table->foreignId('room_layout_id')->constrained('room_layouts')->cascadeOnDelete();
                $table->unsignedTinyInteger('x_position');
                $table->unsignedTinyInteger('y_position');
                $table->enum('cell_type', ['seat', 'aisle']);
                $table->foreignId('seat_id')->nullable()->constrained('seats')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['room_layout_id', 'x_position', 'y_position'], 'room_layout_cells_coordinate_unique');
                $table->unique(['room_layout_id', 'seat_id'], 'room_layout_cells_seat_unique');
            });
        }

        if (! Schema::hasTable('showtimes')) {
            throw new RuntimeException('Cannot add versioned room layouts because the showtimes table is missing. No DDL was executed on showtimes.');
        }

        $layoutColumnAdded = false;
        if (! Schema::hasColumn('showtimes', 'room_layout_id')) {
            Schema::table('showtimes', function (Blueprint $table): void {
                // Nullable only for safe deployment over legacy data. Application services
                // require this value for every new or updated operational showtime.
                $table->unsignedBigInteger('room_layout_id')->nullable()->after('room_id');
            });
            $layoutColumnAdded = true;
        }

        if ($layoutColumnAdded
            || $this->foreignKeyName('showtimes', ['room_layout_id'], 'room_layouts', ['id']) === null) {
            Schema::table('showtimes', function (Blueprint $table): void {
                $table->foreign('room_layout_id', self::SHOWTIME_LAYOUT_FOREIGN)
                    ->references('id')->on('room_layouts')->restrictOnDelete();
            });
        }

        if ($this->indexName('showtimes', ['room_id', 'room_layout_id']) === null) {
            Schema::table('showtimes', function (Blueprint $table): void {
                $table->index(['room_id', 'room_layout_id'], self::SHOWTIME_LAYOUT_INDEX);
            });
        }
    }

    public function down(): void
    {
        $this->assertRollbackHasNoLayoutHistory();

        if (Schema::hasTable('showtimes') && Schema::hasColumn('showtimes', 'room_layout_id')) {
            $indexes = $this->indexesContainingColumn('showtimes', 'room_layout_id');
            $roomForeign = $this->foreignKeyName('showtimes', ['room_id'], 'rooms', ['id']);
            $restoreRoomForeign = false;

            $this->dropForeignKeyIfPresent('showtimes', ['room_layout_id'], 'room_layouts', ['id']);

            // MySQL may discard the original single-column room_id index after the
            // composite index is created and then use the composite index for this FK.
            // Drop that FK before every index that can support it, then restore it.
            if ($indexes !== [] && $roomForeign !== null) {
                $this->dropForeignKey('showtimes', ['room_id'], $roomForeign);
                $restoreRoomForeign = true;
            }

            foreach ($indexes as $index) {
                $this->dropIndex('showtimes', $index);
            }

            Schema::table('showtimes', function (Blueprint $table): void {
                $table->dropColumn('room_layout_id');
            });

            $this->restoreShowtimeRoomForeignIfMissing($restoreRoomForeign);
        } elseif (Schema::hasTable('showtimes')) {
            // Resume safely after a MySQL auto-committed partial down().
            $this->restoreShowtimeRoomForeignIfMissing();
        }

        Schema::dropIfExists('room_layout_cells');
        Schema::dropIfExists('room_layouts');
    }

    private function assertRollbackHasNoLayoutHistory(): void
    {
        $referencedShowtimes = Schema::hasTable('showtimes') && Schema::hasColumn('showtimes', 'room_layout_id')
            ? DB::table('showtimes')->whereNotNull('room_layout_id')->count()
            : 0;
        $cells = Schema::hasTable('room_layout_cells') ? DB::table('room_layout_cells')->count() : 0;
        $layouts = Schema::hasTable('room_layouts') ? DB::table('room_layouts')->count() : 0;

        if ($referencedShowtimes !== 0 || $cells !== 0 || $layouts !== 0) {
            throw new RuntimeException(
                'Cannot roll back versioned room layouts while protected layout history exists: '
                ."referenced_showtimes={$referencedShowtimes}, room_layout_cells={$cells}, room_layouts={$layouts}. "
                .'No rows or schema objects were changed.',
            );
        }
    }

    private function restoreShowtimeRoomForeignIfMissing(bool $force = false): void
    {
        if (! Schema::hasColumn('showtimes', 'room_id')
            || ! Schema::hasTable('rooms')
            || (! $force && $this->foreignKeyName('showtimes', ['room_id'], 'rooms', ['id']) !== null)) {
            return;
        }

        $orphan = DB::table('showtimes')
            ->leftJoin('rooms', 'rooms.id', '=', 'showtimes.room_id')
            ->whereNull('rooms.id')
            ->value('showtimes.id');
        if ($orphan !== null) {
            throw new RuntimeException(
                "Cannot restore the showtimes room foreign key because showtime {$orphan} references a missing room. No rows were changed.",
            );
        }

        Schema::table('showtimes', function (Blueprint $table): void {
            $table->foreign('room_id', self::SHOWTIME_ROOM_FOREIGN)
                ->references('id')->on('rooms')->cascadeOnDelete();
        });
    }

    /** @param list<string> $columns */
    private function dropForeignKey(string $tableName, array $columns, string $name): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($columns, $name): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? $columns : $name);
        });
    }

    /** @param list<string> $columns @param list<string> $foreignColumns */
    private function dropForeignKeyIfPresent(
        string $tableName,
        array $columns,
        string $foreignTable,
        array $foreignColumns,
    ): void {
        $name = $this->foreignKeyName($tableName, $columns, $foreignTable, $foreignColumns);
        if ($name !== null) {
            $this->dropForeignKey($tableName, $columns, $name);
        }
    }

    private function dropIndex(string $tableName, string $name): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($name): void {
            $table->dropIndex($name);
        });
    }

    /** @param list<string> $columns @param list<string> $foreignColumns */
    private function foreignKeyName(
        string $tableName,
        array $columns,
        string $foreignTable,
        array $foreignColumns,
    ): ?string {
        if (! Schema::hasTable($tableName)) {
            return null;
        }

        $foreign = collect(Schema::getForeignKeys($tableName))->first(
            fn (array $candidate): bool => ($candidate['columns'] ?? []) === $columns
                && ($candidate['foreign_table'] ?? null) === $foreignTable
                && ($candidate['foreign_columns'] ?? []) === $foreignColumns,
        );

        return DB::getDriverName() === 'sqlite'
            ? implode('_', $columns).'_foreign'
            : ($foreign['name'] ?? null);
    }

    /** @param list<string> $columns */
    private function indexName(string $tableName, array $columns): ?string
    {
        $index = collect(Schema::getIndexes($tableName))->first(
            fn (array $candidate): bool => ($candidate['columns'] ?? []) === $columns,
        );

        return $index['name'] ?? null;
    }

    /** @return list<string> */
    private function indexesContainingColumn(string $tableName, string $column): array
    {
        return collect(Schema::getIndexes($tableName))
            ->filter(fn (array $index): bool => in_array($column, $index['columns'] ?? [], true))
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== 'PRIMARY')
            ->values()
            ->all();
    }
};
