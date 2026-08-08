<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_checkin_events')) {
            return;
        }

        $driver = DB::getDriverName();
        Schema::create('ticket_checkin_events', function (Blueprint $table) use ($driver): void {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('showtime_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
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
            $table->index(['booking_id', 'result'], 'ticket_checkins_booking_result_index');
            $table->index('booking_id', 'ticket_checkins_booking_index');
            $table->index('showtime_id', 'ticket_checkins_showtime_index');
            $table->index('actor_user_id', 'ticket_checkins_actor_index');

            if ($driver === 'sqlite') {
                $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
                $table->foreign('showtime_id')->references('id')->on('showtimes')->nullOnDelete();
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        if ($driver !== 'sqlite') {
            Schema::table('ticket_checkin_events', function (Blueprint $table): void {
                $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
                $table->foreign('showtime_id')->references('id')->on('showtimes')->nullOnDelete();
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        $this->createAppendOnlyTriggers();
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket_checkin_events') && DB::table('ticket_checkin_events')->exists()) {
            throw new RuntimeException('Ticket check-in events are append-only. Rollback refused while history exists.');
        }

        $this->dropAppendOnlyTriggers();
        Schema::dropIfExists('ticket_checkin_events');
    }

    private function createAppendOnlyTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER ticket_checkin_events_prevent_update BEFORE UPDATE ON ticket_checkin_events BEGIN SELECT RAISE(ABORT, 'ticket_checkin_events are append-only'); END");
            DB::statement("CREATE TRIGGER ticket_checkin_events_prevent_delete BEFORE DELETE ON ticket_checkin_events BEGIN SELECT RAISE(ABORT, 'ticket_checkin_events are append-only'); END");
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER ticket_checkin_events_prevent_update BEFORE UPDATE ON ticket_checkin_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ticket_checkin_events are append-only'");
            DB::unprepared("CREATE TRIGGER ticket_checkin_events_prevent_delete BEFORE DELETE ON ticket_checkin_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ticket_checkin_events are append-only'");
        }
    }

    private function dropAppendOnlyTriggers(): void
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
