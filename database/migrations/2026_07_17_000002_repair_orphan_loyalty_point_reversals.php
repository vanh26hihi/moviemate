<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $orphanReversals = DB::table('loyalty_point_transactions as reversals')
                ->where('reversals.type', 'reverse')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('loyalty_point_transactions as earns')
                        ->whereColumn('earns.booking_id', 'reversals.booking_id')
                        ->where('earns.type', 'earn');
                })
                ->select('reversals.id', 'reversals.user_id', 'reversals.points')
                ->get();

            foreach ($orphanReversals as $reversal) {
                $points = abs((int) $reversal->points);

                DB::table('users')->where('id', $reversal->user_id)->increment('loyalty_points', $points);
                DB::table('users')->where('id', $reversal->user_id)->increment('lifetime_loyalty_points', $points);
                DB::table('loyalty_point_transactions')->where('id', $reversal->id)->delete();
            }
        });
    }

    public function down(): void
    {
        // Đây là migration sửa dữ liệu sai; không tái tạo giao dịch trừ điểm không hợp lệ.
    }
};
