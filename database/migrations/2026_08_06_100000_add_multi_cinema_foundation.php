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
            $table->string('code', 32)->nullable()->after('id');
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh')->after('country');
            $table->unique('code');
        });

        Schema::create('user_cinema_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cinema_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->unique(['user_id', 'cinema_id'], 'user_cinema_assignment_unique');
            $table->index(['cinema_id', 'status'], 'cinema_assignment_status_index');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('cinema_id')->nullable()->after('showtime_id')
                ->constrained()->restrictOnDelete();
            $table->index(['cinema_id', 'created_at'], 'bookings_cinema_created_index');
        });

        DB::table('bookings')->whereNull('cinema_id')->orderBy('id')->chunkById(500, function ($bookings): void {
            $showtimeCinemaIds = DB::table('showtimes')
                ->whereIn('id', $bookings->pluck('showtime_id')->filter())
                ->pluck('cinema_id', 'id');

            foreach ($bookings as $booking) {
                $cinemaId = $showtimeCinemaIds->get($booking->showtime_id);
                if ($cinemaId !== null) {
                    DB::table('bookings')->where('id', $booking->id)->update(['cinema_id' => $cinemaId]);
                }
            }
        });

        $this->seedPermissions();
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_cinema_created_index');
            $table->dropConstrainedForeignId('cinema_id');
        });

        Schema::dropIfExists('user_cinema_assignments');

        Schema::table('cinemas', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'timezone']);
        });
    }

    private function seedPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $definitions = [
            'cinemas.view' => ['Xem danh sách chi nhánh', ['admin', 'manager', 'staff']],
            'cinemas.manage' => ['Quản lý chi nhánh', ['admin']],
            'cinema_assignments.view' => ['Xem phân công chi nhánh', ['admin', 'manager']],
            'cinema_assignments.manage' => ['Quản lý phân công chi nhánh', ['admin', 'manager']],
        ];
        $now = now();

        foreach ($definitions as $slug => [$name, $roleSlugs]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'group' => str($slug)->before('.')->toString(), 'updated_at' => $now, 'created_at' => $now],
            );
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->whereIn('slug', $roleSlugs)->pluck('id') as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
