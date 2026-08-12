<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropCheckinTriggers();
        Schema::dropIfExists('ticket_checkin_events');

        if (Schema::hasColumn('admission_tickets', 'used_by_user_id')) {
            Schema::table('admission_tickets', function (Blueprint $table): void {
                $table->dropForeign(['used_by_user_id']);
                $table->dropIndex(['used_at']);
                $table->dropColumn(['used_at', 'used_by_user_id']);
            });
        }

        if (Schema::hasColumn('bookings', 'used_at')) {
            Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('used_at'));
        }

        $obsolete = DB::table('permissions')
            ->whereIn('slug', ['ticket_checkins.view', 'tickets.checkin'])
            ->pluck('id');
        if ($obsolete->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $obsolete)->delete();
            DB::table('permissions')->whereIn('id', $obsolete)->delete();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Digital ticket check-in removal is intentionally irreversible.');
    }

    private function dropCheckinTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS ticket_checkin_events_prevent_update');
            DB::statement('DROP TRIGGER IF EXISTS ticket_checkin_events_prevent_delete');
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS ticket_checkin_events_prevent_update');
            DB::unprepared('DROP TRIGGER IF EXISTS ticket_checkin_events_prevent_delete');
        }
    }
};
