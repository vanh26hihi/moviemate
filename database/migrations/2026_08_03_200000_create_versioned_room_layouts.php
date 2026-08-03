<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::table('showtimes', function (Blueprint $table) {
            // Nullable only for safe deployment over legacy data. Application services
            // require this value for every new or updated operational showtime.
            $table->foreignId('room_layout_id')->nullable()->after('room_id')
                ->constrained('room_layouts')->restrictOnDelete();
            $table->index(['room_id', 'room_layout_id']);
        });
    }

    public function down(): void
    {
        Schema::table('showtimes', function (Blueprint $table) {
            $table->dropIndex(['room_id', 'room_layout_id']);
            $table->dropForeign(['room_layout_id']);
            $table->dropColumn('room_layout_id');
        });

        Schema::dropIfExists('room_layout_cells');
        Schema::dropIfExists('room_layouts');
    }
};
