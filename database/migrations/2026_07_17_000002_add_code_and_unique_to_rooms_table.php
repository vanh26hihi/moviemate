<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Nullable keeps the restored fresh schema compatible with the current Room controller.
            $table->string('code', 32)->nullable()->after('cinema_id');
            $table->unique(['cinema_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropUnique(['cinema_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
