<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cinemas', function (Blueprint $table): void {
            $table->string('district', 120)->nullable()->after('city')->index();
        });

        if (! DB::table('cinemas')->where('code', 'CG')->exists()) {
            DB::table('cinemas')->where('is_primary', true)->whereNull('code')->limit(1)->update(['code' => 'CG']);
        }

        foreach (['CG' => 'Cầu Giấy', 'HD' => 'Hà Đông', 'NTL' => 'Nam Từ Liêm'] as $code => $district) {
            DB::table('cinemas')->where('code', $code)->whereNull('district')->update(['district' => $district]);
        }
    }

    public function down(): void
    {
        Schema::table('cinemas', function (Blueprint $table): void {
            $table->dropIndex(['district']);
            $table->dropColumn('district');
        });
    }
};
