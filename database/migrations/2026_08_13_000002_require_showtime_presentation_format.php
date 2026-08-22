<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $nullCount = DB::table('showtimes')->whereNull('presentation_format_id')->count();
        if ($nullCount > 0) {
            throw new RuntimeException(
                "Cannot require showtimes.presentation_format_id while {$nullCount} showtime row(s) are NULL. Reset and reseed disposable local data or correct the source data explicitly.",
            );
        }

        Schema::table('showtimes', function (Blueprint $table): void {
            $table->unsignedBigInteger('presentation_format_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('showtimes', function (Blueprint $table): void {
            $table->unsignedBigInteger('presentation_format_id')->nullable()->change();
        });
    }
};
