<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_layout_cells', function (Blueprint $table): void {
            $table->enum('cell_type', ['seat', 'aisle', 'blocked'])->change();
        });
    }

    public function down(): void
    {
        $blocked = DB::table('room_layout_cells')->where('cell_type', 'blocked')->count();
        if ($blocked !== 0) {
            throw new RuntimeException("Cannot remove BLOCKED layout-cell support while {$blocked} blocked cells exist.");
        }

        Schema::table('room_layout_cells', function (Blueprint $table): void {
            $table->enum('cell_type', ['seat', 'aisle'])->change();
        });
    }
};
