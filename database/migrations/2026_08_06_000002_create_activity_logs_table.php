<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('actor_role_snapshot', 64)->nullable();
            $table->string('action', 100)->index();
            $table->string('subject_type', 191)->index();
            $table->string('subject_id', 100)->nullable()->index();
            $table->string('subject_label', 255)->nullable();
            $table->string('request_id', 100)->nullable()->index();
            $table->string('route_name', 191)->nullable()->index();
            $table->string('method', 10)->nullable();
            $table->string('safe_ip_hash', 64)->nullable();
            $table->string('user_agent_summary', 100)->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id', 'created_at'], 'activity_logs_subject_time_index');
        });

        $this->createAppendOnlyTriggers();
    }

    public function down(): void
    {
        if (Schema::hasTable('activity_logs') && DB::table('activity_logs')->exists()) {
            throw new RuntimeException('Activity logs are append-only. Rollback refused while history exists.');
        }

        $this->dropAppendOnlyTriggers();
        Schema::dropIfExists('activity_logs');
    }

    private function createAppendOnlyTriggers(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER activity_logs_prevent_update BEFORE UPDATE ON activity_logs BEGIN SELECT RAISE(ABORT, 'activity_logs are append-only'); END");
            DB::statement("CREATE TRIGGER activity_logs_prevent_delete BEFORE DELETE ON activity_logs BEGIN SELECT RAISE(ABORT, 'activity_logs are append-only'); END");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER activity_logs_prevent_update BEFORE UPDATE ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_logs are append-only'");
            DB::unprepared("CREATE TRIGGER activity_logs_prevent_delete BEFORE DELETE ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_logs are append-only'");
        }
    }

    private function dropAppendOnlyTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS activity_logs_prevent_update');
            DB::statement('DROP TRIGGER IF EXISTS activity_logs_prevent_delete');
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS activity_logs_prevent_update');
            DB::unprepared('DROP TRIGGER IF EXISTS activity_logs_prevent_delete');
        }
    }
};
