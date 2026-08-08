<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_ticket_prints')) {
            Schema::create('booking_ticket_prints', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
                $table->string('status', 40)->index();
                $table->unsignedInteger('attempts_count')->default(0);
                $table->foreignId('printed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('printed_at')->nullable()->index();
                $table->foreignId('last_failed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_failed_at')->nullable();
                $table->string('last_failure_code', 40)->nullable();
                $table->foreignId('retry_authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('retry_authorized_at')->nullable();
                $table->uuid('active_operation_id')->nullable()->unique();
                $table->char('active_operation_token_hash', 64)->nullable();
                $table->foreignId('active_operator_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('active_operation_expires_at')->nullable()->index();
                $table->uuid('last_completed_operation_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('booking_ticket_print_events')) {
            Schema::create('booking_ticket_print_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('booking_ticket_print_id')->constrained('booking_ticket_prints')->restrictOnDelete();
                $table->foreignId('booking_id')->constrained()->restrictOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role_snapshot', 64)->nullable();
                $table->string('event_type', 40)->index();
                $table->unsignedInteger('attempt_number');
                $table->uuid('operation_id')->nullable();
                $table->string('failure_code', 40)->nullable();
                $table->string('safe_note', 300)->nullable();
                $table->string('request_id', 100)->nullable()->index();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['booking_id', 'id'], 'ticket_print_events_booking_id_index');
                $table->unique(['operation_id', 'event_type'], 'ticket_print_events_operation_type_unique');
            });

            $this->createAppendOnlyTriggers();
        }

        $this->installPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('booking_ticket_print_events') && DB::table('booking_ticket_print_events')->exists()) {
            throw new RuntimeException('Ticket print events are append-only. Rollback refused while history exists.');
        }
        if (Schema::hasTable('booking_ticket_prints') && DB::table('booking_ticket_prints')->exists()) {
            throw new RuntimeException('Ticket print state is operational history. Rollback refused while rows exist.');
        }

        $this->dropAppendOnlyTriggers();
        Schema::dropIfExists('booking_ticket_print_events');
        Schema::dropIfExists('booking_ticket_prints');
    }

    private function installPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissions = [
            'tickets.lookup' => ['Tra cứu vé', ['admin', 'manager', 'staff']],
            'tickets.print.override' => ['Cho phép in lại vé', ['admin', 'manager']],
            'ticket_prints.view' => ['Xem lịch sử in vé', ['admin', 'manager']],
        ];
        $now = now();
        foreach ($permissions as $slug => [$name, $roles]) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name,
                'group' => str($slug)->before('.')->toString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->whereIn('slug', $roles)->pluck('id') as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function createAppendOnlyTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER booking_ticket_print_events_prevent_update BEFORE UPDATE ON booking_ticket_print_events BEGIN SELECT RAISE(ABORT, 'booking_ticket_print_events are append-only'); END");
            DB::statement("CREATE TRIGGER booking_ticket_print_events_prevent_delete BEFORE DELETE ON booking_ticket_print_events BEGIN SELECT RAISE(ABORT, 'booking_ticket_print_events are append-only'); END");
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER booking_ticket_print_events_prevent_update BEFORE UPDATE ON booking_ticket_print_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'booking_ticket_print_events are append-only'");
            DB::unprepared("CREATE TRIGGER booking_ticket_print_events_prevent_delete BEFORE DELETE ON booking_ticket_print_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'booking_ticket_print_events are append-only'");
        }
    }

    private function dropAppendOnlyTriggers(): void
    {
        foreach (['booking_ticket_print_events_prevent_update', 'booking_ticket_print_events_prevent_delete'] as $trigger) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }
    }
};
