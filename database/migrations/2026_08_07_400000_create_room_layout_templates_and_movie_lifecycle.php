<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_layout_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('room_type', 20)->nullable();
            $table->unsignedTinyInteger('rows');
            $table->unsignedTinyInteger('columns');
            $table->string('screen_position', 10)->default('top');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'name']);
        });

        Schema::create('room_layout_template_cells', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_layout_template_id')->constrained('room_layout_templates')->cascadeOnDelete();
            $table->unsignedTinyInteger('x_position');
            $table->unsignedTinyInteger('y_position');
            $table->string('cell_type', 20);
            $table->string('seat_type', 20)->nullable();
            $table->string('seat_label', 16)->nullable();
            $table->string('seat_unit_key', 40)->nullable();
            $table->string('pair_key', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['room_layout_template_id', 'x_position', 'y_position'],
                'room_layout_template_cells_coordinate_unique',
            );
            $table->unique(
                ['room_layout_template_id', 'seat_label'],
                'room_layout_template_cells_label_unique',
            );
        });

        Schema::table('room_layouts', function (Blueprint $table): void {
            $table->text('change_note')->nullable()->after('name');
            $table->foreignId('source_template_id')->nullable()->after('change_note')
                ->constrained('room_layout_templates')->nullOnDelete();
            $table->string('source_template_name_snapshot')->nullable()->after('source_template_id');
        });

        Schema::table('movies', function (Blueprint $table): void {
            $table->string('status', 30)->default('draft')->change();
        });
        DB::table('movies')->where('status', 'stopped')->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        $templateUsage = Schema::hasTable('room_layouts') && Schema::hasColumn('room_layouts', 'source_template_id')
            ? DB::table('room_layouts')->whereNotNull('source_template_id')->count()
            : 0;
        $newMovieStatuses = Schema::hasTable('movies')
            ? DB::table('movies')->whereIn('status', ['draft', 'inactive', 'archived'])->count()
            : 0;
        if ($templateUsage !== 0 || $newMovieStatuses !== 0) {
            throw new RuntimeException(
                "Cannot roll back R7 while protected history exists: template_layouts={$templateUsage}, lifecycle_movies={$newMovieStatuses}. No schema was changed.",
            );
        }

        Schema::table('room_layouts', function (Blueprint $table): void {
            $table->dropForeign(['source_template_id']);
            $table->dropColumn(['change_note', 'source_template_id', 'source_template_name_snapshot']);
        });
        Schema::dropIfExists('room_layout_template_cells');
        Schema::dropIfExists('room_layout_templates');

        Schema::table('movies', function (Blueprint $table): void {
            $table->string('status', 30)->default('now_showing')->change();
        });
    }
};
