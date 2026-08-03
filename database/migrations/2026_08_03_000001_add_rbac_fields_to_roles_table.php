<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('description')->nullable()->after('slug');
            $table->boolean('is_system')->default(true)->after('description');
        });

        DB::table('roles')->orderBy('id')->get()->each(function (object $role): void {
            $knownSlugs = [
                'admin' => 'admin',
                'manager' => 'manager',
                'staff' => 'staff',
                'user' => 'user',
            ];
            $base = $knownSlugs[Str::lower($role->name)] ?? Str::slug($role->name);
            $slug = $base !== '' ? $base : 'role-'.$role->id;

            if (DB::table('roles')->where('slug', $slug)->where('id', '!=', $role->id)->exists()) {
                $slug .= '-'.$role->id;
            }

            DB::table('roles')->where('id', $role->id)->update(['slug' => $slug]);
        });

    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description', 'is_system']);
        });
    }
};
