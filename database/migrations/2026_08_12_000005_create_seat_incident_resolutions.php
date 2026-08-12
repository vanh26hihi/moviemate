<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_incident_resolutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seat_incident_impact_id')->unique()->constrained('seat_incident_impacts')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->enum('resolution_type', ['equivalent', 'upgrade', 'requires_refund']);
            $table->foreignId('original_seat_id')->constrained('seats')->restrictOnDelete();
            $table->foreignId('replacement_seat_id')->nullable()->constrained('seats')->restrictOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('original_pre_promotion_amount');
            $table->unsignedBigInteger('replacement_hypothetical_amount')->nullable();
            $table->boolean('reprint_required')->default(false);
            $table->timestamp('reprint_satisfied_at')->nullable();
            $table->string('operational_note', 500)->nullable();
            $table->timestamps();

            $table->index(['operation_id', 'id'], 'seat_incident_resolutions_operation_index');
            $table->index(['resolution_type', 'reprint_required', 'reprint_satisfied_at'], 'seat_incident_resolutions_state_index');
        });

        Schema::table('booking_ticket_prints', function (Blueprint $table): void {
            $table->foreignId('active_seat_incident_resolution_id')->nullable()
                ->after('active_operator_user_id')->constrained('seat_incident_resolutions')->restrictOnDelete();
        });

        Schema::table('booking_ticket_print_events', function (Blueprint $table): void {
            $table->foreignId('seat_incident_resolution_id')->nullable()
                ->after('booking_id')->constrained('seat_incident_resolutions')->restrictOnDelete();
            $table->index(['seat_incident_resolution_id', 'id'], 'ticket_print_events_incident_resolution_index');
        });
        $this->restoreSqlitePrintEventTriggers();
    }

    public function down(): void
    {
        Schema::table('booking_ticket_print_events', function (Blueprint $table): void {
            $table->dropForeign(['seat_incident_resolution_id']);
            $table->dropIndex('ticket_print_events_incident_resolution_index');
            $table->dropColumn('seat_incident_resolution_id');
        });
        $this->restoreSqlitePrintEventTriggers();

        Schema::table('booking_ticket_prints', function (Blueprint $table): void {
            $table->dropForeign(['active_seat_incident_resolution_id']);
            $table->dropColumn('active_seat_incident_resolution_id');
        });

        Schema::dropIfExists('seat_incident_resolutions');
    }

    private function restoreSqlitePrintEventTriggers(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        DB::statement('DROP TRIGGER IF EXISTS booking_ticket_print_events_prevent_update');
        DB::statement('DROP TRIGGER IF EXISTS booking_ticket_print_events_prevent_delete');
        DB::statement("CREATE TRIGGER booking_ticket_print_events_prevent_update BEFORE UPDATE ON booking_ticket_print_events BEGIN SELECT RAISE(ABORT, 'booking_ticket_print_events are append-only'); END");
        DB::statement("CREATE TRIGGER booking_ticket_print_events_prevent_delete BEFORE DELETE ON booking_ticket_print_events BEGIN SELECT RAISE(ABORT, 'booking_ticket_print_events are append-only'); END");
    }
};
