<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->where('id', 12)->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        DB::table('rooms')->where('id', 12)->update(['status' => 'active']);
    }
};
