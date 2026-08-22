<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_formats', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('movie_presentation_formats', function (Blueprint $table): void {
            $table->foreignId('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->foreignId('presentation_format_id')->constrained('presentation_formats')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['movie_id', 'presentation_format_id'], 'movie_format_unique');
            $table->index('presentation_format_id', 'movie_format_format_index');
        });

        Schema::create('room_presentation_capabilities', function (Blueprint $table): void {
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('presentation_format_id')->constrained('presentation_formats')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['room_id', 'presentation_format_id'], 'room_capability_unique');
            $table->index('presentation_format_id', 'room_capability_format_index');
        });

        Schema::table('showtimes', function (Blueprint $table): void {
            $table->foreignId('presentation_format_id')->nullable()->after('room_layout_id')
                ->constrained('presentation_formats')->restrictOnDelete();
            $table->index('presentation_format_id');
        });
    }

    public function down(): void
    {
        Schema::table('showtimes', function (Blueprint $table): void {
            $table->dropForeign(['presentation_format_id']);
            $table->dropIndex(['presentation_format_id']);
            $table->dropColumn('presentation_format_id');
        });

        Schema::dropIfExists('room_presentation_capabilities');
        Schema::dropIfExists('movie_presentation_formats');
        Schema::dropIfExists('presentation_formats');
    }
};
