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
        $needsCode = ! Schema::hasColumn('room_types', 'code');
        $needsActive = ! Schema::hasColumn('room_types', 'is_active');
        $needsCreatedBy = ! Schema::hasColumn('room_types', 'created_by_user_id');
        $needsUpdatedBy = ! Schema::hasColumn('room_types', 'updated_by_user_id');

        Schema::table('room_types', function (Blueprint $table) use ($needsCode, $needsActive, $needsCreatedBy, $needsUpdatedBy): void {
            if ($needsCode) {
                $table->string('code', 40)->nullable()->unique()->after('id');
            }
            if ($needsActive) {
                $table->boolean('is_active')->default(true)->index()->after('description');
            }
            if ($needsCreatedBy) {
                $table->foreignId('created_by_user_id')->nullable()->after('sort_order')
                    ->constrained('users')->nullOnDelete();
            }
            if ($needsUpdatedBy) {
                $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')
                    ->constrained('users')->nullOnDelete();
            }
        });

        foreach (DB::table('room_types')->orderBy('id')->get() as $type) {
            $code = $this->code((string) ($type->slug ?: $type->name));
            if (DB::table('room_types')->where('code', $code)->where('id', '!=', $type->id)->exists()) {
                $code = Str::limit($code, 32, '').'_'.$type->id;
            }
            DB::table('room_types')->where('id', $type->id)->update([
                'code' => $code,
                'is_active' => (bool) $type->status,
            ]);
        }

        $sourceTypes = collect(['2D', '3D', 'IMAX']);
        if (Schema::hasTable('rooms')) {
            $sourceTypes = $sourceTypes->merge(DB::table('rooms')->whereNotNull('room_type')->distinct()->pluck('room_type'));
        }
        if (Schema::hasTable('cinema_pricing_rules')) {
            $sourceTypes = $sourceTypes->merge(DB::table('cinema_pricing_rules')->whereNotNull('room_type')->distinct()->pluck('room_type'));
        }

        foreach ($sourceTypes->filter()->unique() as $source) {
            $name = Str::limit(trim((string) $source), 120, '');
            $code = $this->code($name);
            $existing = DB::table('room_types')->where('code', $code)->first();
            if (! $existing) {
                DB::table('room_types')->insert([
                    'code' => $code,
                    'name' => $name,
                    'slug' => $code,
                    'description' => null,
                    'is_active' => true,
                    'status' => true,
                    'sort_order' => match ($code) {
                        '2D' => 10, '3D' => 20, 'IMAX' => 30, default => 100
                    },
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasColumn('rooms', 'room_type_id')) {
                $id = DB::table('room_types')->where('code', $code)->value('id');
                DB::table('rooms')->where('room_type', $source)->update([
                    'room_type' => $code,
                    'room_type_id' => $id,
                ]);
            }
            if (Schema::hasTable('cinema_pricing_rules')) {
                DB::table('cinema_pricing_rules')->where('room_type', $source)->update(['room_type' => $code]);
            }
        }

        $this->installPermissions();
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table): void {
            foreach (['created_by_user_id', 'updated_by_user_id'] as $column) {
                if (Schema::hasColumn('room_types', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            if (Schema::hasColumn('room_types', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('room_types', 'code')) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            }
        });
    }

    private function code(string $value): string
    {
        $code = preg_replace('/[^A-Z0-9]+/', '_', strtoupper(Str::ascii(trim($value)))) ?? '';
        $code = trim($code, '_');

        return Str::limit($code !== '' ? $code : 'ROOM_TYPE', 40, '');
    }

    private function installPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role') || ! Schema::hasTable('roles')) {
            return;
        }

        $permissions = [
            'room_types.view' => 'Xem danh mục loại phòng',
            'room_types.manage' => 'Quản lý danh mục loại phòng',
        ];
        $now = now();
        foreach ($permissions as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name, 'group' => 'rooms', 'description' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            $roleSlugs = $slug === 'room_types.view' ? ['admin', 'manager'] : ['admin'];
            foreach (DB::table('roles')->whereIn('slug', $roleSlugs)->pluck('id') as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId, 'role_id' => $roleId,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }
};
