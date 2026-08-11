<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropLegacyOperationalTables();

        Schema::create('admission_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_seat_id')->unique()->constrained('booking_seats')->restrictOnDelete();
            $table->string('ticket_code', 40)->unique();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable()->index();
            $table->foreignId('last_printed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable()->index();
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['booking_id', 'id'], 'admission_tickets_booking_id_index');
        });

        Schema::create('food_pickup_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->string('voucher_code', 40)->unique();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable()->index();
            $table->foreignId('last_printed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('food_pickup_voucher_print_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('food_pickup_voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('print_number');
            $table->string('reason', 300)->nullable();
            $table->timestamp('printed_at')->useCurrent()->index();
            $table->unique(['food_pickup_voucher_id', 'print_number'], 'food_voucher_print_number_unique');
        });

        Schema::create('booking_ticket_prints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_ticket_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
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
            $table->index(['booking_id', 'id'], 'ticket_prints_booking_id_index');
        });

        Schema::create('booking_ticket_print_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_ticket_print_id')->constrained('booking_ticket_prints')->restrictOnDelete();
            $table->foreignId('admission_ticket_id')->constrained()->restrictOnDelete();
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
            $table->index(['admission_ticket_id', 'id'], 'ticket_print_events_ticket_id_index');
            $table->index(['booking_id', 'id'], 'ticket_print_events_booking_id_index');
            $table->unique(['operation_id', 'event_type'], 'ticket_print_events_operation_type_unique');
        });

        Schema::create('ticket_checkin_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('accepted_ticket_id')->nullable()->unique()->constrained('admission_tickets')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('showtime_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role_snapshot', 64)->nullable();
            $table->string('result', 32)->index();
            $table->string('reason_code', 64)->nullable();
            $table->timestamp('scanned_at')->index();
            $table->string('request_id', 100)->nullable()->index();
            $table->string('route_name', 191)->nullable();
            $table->string('safe_ip_hash', 64)->nullable();
            $table->string('user_agent_summary', 100)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['admission_ticket_id', 'result'], 'ticket_checkins_ticket_result_index');
            $table->index(['booking_id', 'result'], 'ticket_checkins_booking_result_index');
            $table->index('admission_ticket_id', 'ticket_checkins_ticket_index');
            $table->index('booking_id', 'ticket_checkins_booking_index');
            $table->index('showtime_id', 'ticket_checkins_showtime_index');
            $table->index('actor_user_id', 'ticket_checkins_actor_index');
        });

        $this->createAppendOnlyTriggers();
    }

    public function down(): void
    {
        $this->dropAppendOnlyTriggers();
        Schema::dropIfExists('ticket_checkin_events');
        Schema::dropIfExists('booking_ticket_print_events');
        Schema::dropIfExists('booking_ticket_prints');
        Schema::dropIfExists('food_pickup_voucher_print_events');
        Schema::dropIfExists('food_pickup_vouchers');
        Schema::dropIfExists('admission_tickets');
    }

    private function dropLegacyOperationalTables(): void
    {
        $this->dropAppendOnlyTriggers();
        Schema::dropIfExists('ticket_checkin_events');
        Schema::dropIfExists('booking_ticket_print_events');
        Schema::dropIfExists('booking_ticket_prints');
    }

    private function createAppendOnlyTriggers(): void
    {
        $tables = ['ticket_checkin_events', 'booking_ticket_print_events', 'food_pickup_voucher_print_events'];
        foreach ($tables as $table) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("CREATE TRIGGER {$table}_prevent_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, '{$table} are append-only'); END");
                DB::statement("CREATE TRIGGER {$table}_prevent_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, '{$table} are append-only'); END");
            } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::unprepared("CREATE TRIGGER {$table}_prevent_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} are append-only'");
                DB::unprepared("CREATE TRIGGER {$table}_prevent_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} are append-only'");
            }
        }
    }

    private function dropAppendOnlyTriggers(): void
    {
        $triggers = [
            'ticket_checkin_events_prevent_update', 'ticket_checkin_events_prevent_delete',
            'booking_ticket_print_events_prevent_update', 'booking_ticket_print_events_prevent_delete',
            'food_pickup_voucher_print_events_prevent_update', 'food_pickup_voucher_print_events_prevent_delete',
        ];
        foreach ($triggers as $trigger) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }
    }
};
