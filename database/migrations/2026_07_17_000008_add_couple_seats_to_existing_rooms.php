<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->orderBy('id')->pluck('id')->each(function ($roomId) {
            $lastRow = DB::table('seats')->where('room_id', $roomId)->max('row');

            if ($lastRow && ! DB::table('seats')->where('room_id', $roomId)->where('type', 'couple')->exists()) {
                DB::table('seats')->where('room_id', $roomId)->where('row', $lastRow)->update([
                    'type' => 'couple',
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Preserve manually configured seat types when rolling back.
    }
};
