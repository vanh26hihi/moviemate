<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('layout_style', 20)->default('standard')->after('room_type');
        });

        $styles = ['standard', 'staggered', 'curved'];

        DB::table('rooms')->orderBy('cinema_id')->orderBy('id')->pluck('id')
            ->each(fn ($roomId, $index) => DB::table('rooms')->where('id', $roomId)->update([
                'layout_style' => $styles[$index % count($styles)],
            ]));
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('layout_style');
        });
    }
};
