<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TRIGGERS = [
        'promotions_used_definition_guard',
        'promotion_cinema_used_insert_guard',
        'promotion_cinema_used_update_guard',
        'promotion_cinema_used_delete_guard',
        'booking_promotions_insert_guard',
        'booking_promotions_update_guard',
        'booking_promotions_state_update_guard',
        'booking_promotions_delete_guard',
    ];

    public function up(): void
    {
        if (Schema::hasTable('booking_discount_codes') && DB::table('booking_discount_codes')->exists()) {
            throw new RuntimeException(
                'Phase 7D cannot fabricate normalized Promotion snapshots from legacy booking_discount_codes. '
                .'Reset this disposable local database or perform an explicitly audited historical migration.'
            );
        }

        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->unsignedBigInteger('discount_amount_vnd')->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->unsignedBigInteger('maximum_discount_vnd')->nullable();
            $table->unsignedBigInteger('minimum_order_vnd')->default(0);
            // Timezone-less local wall-clock values; evaluated in the Booking Cinema timezone.
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('global_usage_limit')->nullable();
            $table->unsignedInteger('per_user_usage_limit')->nullable();
            $table->boolean('registered_users_only')->default(false);
            $table->boolean('first_order_only')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('promotion_cinema', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('cinema_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'cinema_id']);
        });

        Schema::create('booking_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained('promotions')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code_snapshot', 50);
            $table->string('name_snapshot');
            $table->string('type_snapshot', 20);
            $table->unsignedBigInteger('discount_amount_vnd_snapshot')->nullable();
            $table->unsignedTinyInteger('discount_percent_snapshot')->nullable();
            $table->unsignedBigInteger('maximum_discount_vnd_snapshot')->nullable();
            $table->unsignedBigInteger('minimum_order_vnd_snapshot');
            $table->string('scope_kind_snapshot', 20);
            $table->unsignedBigInteger('booking_cinema_id_snapshot');
            $table->string('booking_cinema_code_snapshot', 50)->nullable();
            $table->string('booking_cinema_name_snapshot');
            $table->json('eligible_cinemas_snapshot')->nullable();
            $table->boolean('registered_users_only_snapshot');
            $table->boolean('first_order_only_snapshot');
            $table->unsignedInteger('global_usage_limit_snapshot')->nullable();
            $table->unsignedInteger('per_user_usage_limit_snapshot')->nullable();
            $table->unsignedBigInteger('applied_discount_vnd');
            $table->unsignedBigInteger('gross_before_vnd');
            $table->unsignedBigInteger('final_after_vnd');
            $table->string('status', 20)->default('reserved')->index();
            $table->dateTime('reserved_at');
            $table->dateTime('redeemed_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
            $table->index(['promotion_id', 'status']);
            $table->index(['promotion_id', 'user_id', 'status'], 'promotion_user_usage_index');
        });

        $this->addDatabaseChecks();
        $this->copyUnusedLegacyMasters();
        Schema::dropIfExists('booking_discount_codes');
        Schema::dropIfExists('discount_code_cinema');
        Schema::dropIfExists('discount_codes');
        $this->createGuards();
    }

    public function down(): void
    {
        $this->dropGuards();

        Schema::create('discount_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('discount_type', ['fixed', 'percent']);
            $table->unsignedBigInteger('discount_value');
            $table->unsignedBigInteger('maximum_discount_amount')->nullable();
            $table->unsignedBigInteger('minimum_order_amount')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('total_quota')->nullable();
            $table->unsignedInteger('per_user_quota')->nullable();
            $table->boolean('registered_users_only')->default(false);
            $table->boolean('first_order_only')->default(false);
            $table->boolean('can_combine')->default(false);
            $table->integer('priority')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('discount_code_cinema', function (Blueprint $table): void {
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cinema_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_code_id', 'cinema_id']);
        });
        Schema::create('booking_discount_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discount_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code_snapshot', 50);
            $table->string('name_snapshot');
            $table->string('discount_type_snapshot', 20);
            $table->unsignedBigInteger('discount_value_snapshot');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('subtotal_before');
            $table->unsignedBigInteger('subtotal_after');
            $table->enum('status', ['reserved', 'redeemed', 'released'])->default('reserved')->index();
            $table->timestamp('reserved_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'discount_code_id']);
            $table->index(['discount_code_id', 'status']);
            $table->index(['discount_code_id', 'user_id', 'status'], 'discount_user_quota_index');
        });

        DB::table('promotions')->orderBy('id')->each(function ($promotion): void {
            DB::table('discount_codes')->insert([
                'id' => $promotion->id, 'code' => $promotion->code, 'name' => $promotion->name,
                'description' => $promotion->description, 'discount_type' => $promotion->type === 'percentage' ? 'percent' : 'fixed',
                'discount_value' => $promotion->type === 'fixed' ? $promotion->discount_amount_vnd : $promotion->discount_percent,
                'maximum_discount_amount' => $promotion->maximum_discount_vnd,
                'minimum_order_amount' => $promotion->minimum_order_vnd, 'starts_at' => $promotion->starts_at,
                'ends_at' => $promotion->ends_at, 'is_active' => $promotion->is_active,
                'total_quota' => $promotion->global_usage_limit, 'per_user_quota' => $promotion->per_user_usage_limit,
                'registered_users_only' => $promotion->registered_users_only, 'first_order_only' => $promotion->first_order_only,
                'can_combine' => false, 'priority' => 0, 'created_by_user_id' => $promotion->created_by_user_id,
                'updated_by_user_id' => $promotion->updated_by_user_id, 'archived_at' => $promotion->archived_at,
                'created_at' => $promotion->created_at, 'updated_at' => $promotion->updated_at,
            ]);
        });
        DB::table('promotion_cinema')->orderBy('promotion_id')->each(fn ($scope) => DB::table('discount_code_cinema')->insert([
            'discount_code_id' => $scope->promotion_id, 'cinema_id' => $scope->cinema_id,
        ]));
        DB::table('booking_promotions')->orderBy('id')->each(fn ($usage) => DB::table('booking_discount_codes')->insert([
            'id' => $usage->id, 'booking_id' => $usage->booking_id, 'discount_code_id' => $usage->promotion_id,
            'user_id' => $usage->user_id, 'code_snapshot' => $usage->code_snapshot, 'name_snapshot' => $usage->name_snapshot,
            'discount_type_snapshot' => $usage->type_snapshot === 'percentage' ? 'percent' : 'fixed',
            'discount_value_snapshot' => $usage->type_snapshot === 'fixed' ? $usage->discount_amount_vnd_snapshot : $usage->discount_percent_snapshot,
            'discount_amount' => $usage->applied_discount_vnd, 'subtotal_before' => $usage->gross_before_vnd,
            'subtotal_after' => $usage->final_after_vnd, 'status' => $usage->status, 'reserved_at' => $usage->reserved_at,
            'redeemed_at' => $usage->redeemed_at, 'released_at' => $usage->released_at,
            'created_at' => $usage->created_at, 'updated_at' => $usage->updated_at,
        ]));

        Schema::dropIfExists('booking_promotions');
        Schema::dropIfExists('promotion_cinema');
        Schema::dropIfExists('promotions');
    }

    private function copyUnusedLegacyMasters(): void
    {
        if (! Schema::hasTable('discount_codes')) {
            return;
        }

        DB::table('discount_codes')->orderBy('id')->each(function ($legacy): void {
            $type = $legacy->discount_type === 'percent' ? 'percentage' : 'fixed';
            DB::table('promotions')->insert([
                'id' => $legacy->id, 'code' => mb_strtoupper(trim($legacy->code)), 'name' => $legacy->name,
                'description' => $legacy->description, 'type' => $type,
                'discount_amount_vnd' => $type === 'fixed' ? $legacy->discount_value : null,
                'discount_percent' => $type === 'percentage' ? $legacy->discount_value : null,
                'maximum_discount_vnd' => $type === 'percentage' && (int) $legacy->maximum_discount_amount > 0 ? $legacy->maximum_discount_amount : null,
                'minimum_order_vnd' => $legacy->minimum_order_amount, 'starts_at' => $legacy->starts_at,
                'ends_at' => $legacy->ends_at, 'is_active' => $legacy->is_active,
                'global_usage_limit' => $legacy->total_quota, 'per_user_usage_limit' => $legacy->per_user_quota,
                'registered_users_only' => $legacy->registered_users_only, 'first_order_only' => $legacy->first_order_only,
                'created_by_user_id' => $legacy->created_by_user_id, 'updated_by_user_id' => $legacy->updated_by_user_id,
                'archived_at' => $legacy->archived_at, 'created_at' => $legacy->created_at, 'updated_at' => $legacy->updated_at,
            ]);
        });
        if (Schema::hasTable('discount_code_cinema')) {
            DB::table('discount_code_cinema')->orderBy('discount_code_id')->each(fn ($legacy) => DB::table('promotion_cinema')->insert([
                'promotion_id' => $legacy->discount_code_id, 'cinema_id' => $legacy->cinema_id,
            ]));
        }
    }

    private function addDatabaseChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE promotions ADD CONSTRAINT promotions_type_shape_check CHECK ((type = 'fixed' AND discount_amount_vnd > 0 AND discount_percent IS NULL AND maximum_discount_vnd IS NULL) OR (type = 'percentage' AND discount_amount_vnd IS NULL AND discount_percent BETWEEN 1 AND 100 AND (maximum_discount_vnd IS NULL OR maximum_discount_vnd > 0)))");
        DB::statement('ALTER TABLE promotions ADD CONSTRAINT promotions_usage_limits_check CHECK ((global_usage_limit IS NULL OR global_usage_limit > 0) AND (per_user_usage_limit IS NULL OR per_user_usage_limit > 0))');
        DB::statement('ALTER TABLE promotions ADD CONSTRAINT promotions_date_order_check CHECK (starts_at IS NULL OR ends_at IS NULL OR starts_at < ends_at)');
        DB::statement("ALTER TABLE booking_promotions ADD CONSTRAINT booking_promotions_snapshot_shape_check CHECK ((type_snapshot = 'fixed' AND discount_amount_vnd_snapshot > 0 AND discount_percent_snapshot IS NULL AND maximum_discount_vnd_snapshot IS NULL) OR (type_snapshot = 'percentage' AND discount_amount_vnd_snapshot IS NULL AND discount_percent_snapshot BETWEEN 1 AND 100 AND (maximum_discount_vnd_snapshot IS NULL OR maximum_discount_vnd_snapshot > 0)))");
        DB::statement("ALTER TABLE booking_promotions ADD CONSTRAINT booking_promotions_values_check CHECK (scope_kind_snapshot IN ('global', 'cinema') AND applied_discount_vnd <= gross_before_vnd AND final_after_vnd = gross_before_vnd - applied_discount_vnd AND (global_usage_limit_snapshot IS NULL OR global_usage_limit_snapshot > 0) AND (per_user_usage_limit_snapshot IS NULL OR per_user_usage_limit_snapshot > 0))");
        DB::statement("ALTER TABLE booking_promotions ADD CONSTRAINT booking_promotions_state_check CHECK ((status = 'reserved' AND redeemed_at IS NULL AND released_at IS NULL) OR (status = 'redeemed' AND redeemed_at IS NOT NULL AND released_at IS NULL) OR (status = 'released' AND redeemed_at IS NULL AND released_at IS NOT NULL))");
    }

    private function createGuards(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->createSqliteGuards();

            return;
        }

        DB::unprepared("CREATE TRIGGER promotions_used_definition_guard BEFORE UPDATE ON promotions FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM booking_promotions WHERE promotion_id = OLD.id LIMIT 1) AND NOT (OLD.code <=> NEW.code AND OLD.name <=> NEW.name AND OLD.description <=> NEW.description AND OLD.type <=> NEW.type AND OLD.discount_amount_vnd <=> NEW.discount_amount_vnd AND OLD.discount_percent <=> NEW.discount_percent AND OLD.maximum_discount_vnd <=> NEW.maximum_discount_vnd AND OLD.minimum_order_vnd <=> NEW.minimum_order_vnd AND OLD.starts_at <=> NEW.starts_at AND OLD.ends_at <=> NEW.ends_at AND OLD.global_usage_limit <=> NEW.global_usage_limit AND OLD.per_user_usage_limit <=> NEW.per_user_usage_limit AND OLD.registered_users_only <=> NEW.registered_users_only AND OLD.first_order_only <=> NEW.first_order_only) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Used Promotion business definition is immutable'; END IF; END");
        foreach (['INSERT' => 'NEW', 'UPDATE' => 'OLD', 'DELETE' => 'OLD'] as $operation => $row) {
            DB::unprepared('CREATE TRIGGER promotion_cinema_used_'.strtolower($operation)."_guard BEFORE {$operation} ON promotion_cinema FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM booking_promotions WHERE promotion_id = {$row}.promotion_id LIMIT 1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Used Promotion cinema scope is immutable'; END IF; END");
        }
        DB::unprepared("CREATE TRIGGER booking_promotions_insert_guard BEFORE INSERT ON booking_promotions FOR EACH ROW BEGIN IF NEW.status <> 'reserved' OR NEW.redeemed_at IS NOT NULL OR NEW.released_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Promotion usage must begin reserved'; END IF; END");
        DB::unprepared("CREATE TRIGGER booking_promotions_update_guard BEFORE UPDATE ON booking_promotions FOR EACH ROW BEGIN IF NOT (OLD.booking_id <=> NEW.booking_id AND OLD.promotion_id <=> NEW.promotion_id AND OLD.user_id <=> NEW.user_id AND OLD.code_snapshot <=> NEW.code_snapshot AND OLD.name_snapshot <=> NEW.name_snapshot AND OLD.type_snapshot <=> NEW.type_snapshot AND OLD.discount_amount_vnd_snapshot <=> NEW.discount_amount_vnd_snapshot AND OLD.discount_percent_snapshot <=> NEW.discount_percent_snapshot AND OLD.maximum_discount_vnd_snapshot <=> NEW.maximum_discount_vnd_snapshot AND OLD.minimum_order_vnd_snapshot <=> NEW.minimum_order_vnd_snapshot AND OLD.scope_kind_snapshot <=> NEW.scope_kind_snapshot AND OLD.booking_cinema_id_snapshot <=> NEW.booking_cinema_id_snapshot AND OLD.booking_cinema_code_snapshot <=> NEW.booking_cinema_code_snapshot AND OLD.booking_cinema_name_snapshot <=> NEW.booking_cinema_name_snapshot AND OLD.eligible_cinemas_snapshot <=> NEW.eligible_cinemas_snapshot AND OLD.registered_users_only_snapshot <=> NEW.registered_users_only_snapshot AND OLD.first_order_only_snapshot <=> NEW.first_order_only_snapshot AND OLD.global_usage_limit_snapshot <=> NEW.global_usage_limit_snapshot AND OLD.per_user_usage_limit_snapshot <=> NEW.per_user_usage_limit_snapshot AND OLD.applied_discount_vnd <=> NEW.applied_discount_vnd AND OLD.gross_before_vnd <=> NEW.gross_before_vnd AND OLD.final_after_vnd <=> NEW.final_after_vnd AND OLD.reserved_at <=> NEW.reserved_at AND OLD.created_at <=> NEW.created_at) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Promotion usage snapshot is immutable'; END IF; IF OLD.status <> NEW.status AND NOT (OLD.status = 'reserved' AND NEW.status IN ('redeemed', 'released')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid Promotion usage transition'; END IF; IF OLD.status = NEW.status AND NOT (OLD.redeemed_at <=> NEW.redeemed_at AND OLD.released_at <=> NEW.released_at) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Promotion terminal timestamps are immutable'; END IF; END");
        DB::unprepared("CREATE TRIGGER booking_promotions_delete_guard BEFORE DELETE ON booking_promotions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Promotion usage history cannot be deleted'");
    }

    private function createSqliteGuards(): void
    {
        DB::unprepared("CREATE TRIGGER promotions_used_definition_guard BEFORE UPDATE ON promotions WHEN EXISTS (SELECT 1 FROM booking_promotions WHERE promotion_id = OLD.id) AND (OLD.code IS NOT NEW.code OR OLD.name IS NOT NEW.name OR OLD.description IS NOT NEW.description OR OLD.type IS NOT NEW.type OR OLD.discount_amount_vnd IS NOT NEW.discount_amount_vnd OR OLD.discount_percent IS NOT NEW.discount_percent OR OLD.maximum_discount_vnd IS NOT NEW.maximum_discount_vnd OR OLD.minimum_order_vnd IS NOT NEW.minimum_order_vnd OR OLD.starts_at IS NOT NEW.starts_at OR OLD.ends_at IS NOT NEW.ends_at OR OLD.global_usage_limit IS NOT NEW.global_usage_limit OR OLD.per_user_usage_limit IS NOT NEW.per_user_usage_limit OR OLD.registered_users_only IS NOT NEW.registered_users_only OR OLD.first_order_only IS NOT NEW.first_order_only) BEGIN SELECT RAISE(ABORT, 'Used Promotion business definition is immutable'); END");
        foreach (['INSERT' => 'NEW', 'UPDATE' => 'OLD', 'DELETE' => 'OLD'] as $operation => $row) {
            DB::unprepared('CREATE TRIGGER promotion_cinema_used_'.strtolower($operation)."_guard BEFORE {$operation} ON promotion_cinema WHEN EXISTS (SELECT 1 FROM booking_promotions WHERE promotion_id = {$row}.promotion_id) BEGIN SELECT RAISE(ABORT, 'Used Promotion cinema scope is immutable'); END");
        }
        DB::unprepared("CREATE TRIGGER booking_promotions_insert_guard BEFORE INSERT ON booking_promotions WHEN NEW.status <> 'reserved' OR NEW.redeemed_at IS NOT NULL OR NEW.released_at IS NOT NULL OR NOT (((NEW.type_snapshot = 'fixed') AND NEW.discount_amount_vnd_snapshot > 0 AND NEW.discount_percent_snapshot IS NULL AND NEW.maximum_discount_vnd_snapshot IS NULL) OR ((NEW.type_snapshot = 'percentage') AND NEW.discount_amount_vnd_snapshot IS NULL AND NEW.discount_percent_snapshot BETWEEN 1 AND 100 AND (NEW.maximum_discount_vnd_snapshot IS NULL OR NEW.maximum_discount_vnd_snapshot > 0))) OR NEW.scope_kind_snapshot NOT IN ('global', 'cinema') OR NEW.applied_discount_vnd > NEW.gross_before_vnd OR NEW.final_after_vnd <> NEW.gross_before_vnd - NEW.applied_discount_vnd BEGIN SELECT RAISE(ABORT, 'Invalid Promotion usage snapshot'); END");
        DB::unprepared("CREATE TRIGGER booking_promotions_update_guard BEFORE UPDATE ON booking_promotions WHEN OLD.booking_id IS NOT NEW.booking_id OR OLD.promotion_id IS NOT NEW.promotion_id OR OLD.user_id IS NOT NEW.user_id OR OLD.code_snapshot IS NOT NEW.code_snapshot OR OLD.name_snapshot IS NOT NEW.name_snapshot OR OLD.type_snapshot IS NOT NEW.type_snapshot OR OLD.discount_amount_vnd_snapshot IS NOT NEW.discount_amount_vnd_snapshot OR OLD.discount_percent_snapshot IS NOT NEW.discount_percent_snapshot OR OLD.maximum_discount_vnd_snapshot IS NOT NEW.maximum_discount_vnd_snapshot OR OLD.minimum_order_vnd_snapshot IS NOT NEW.minimum_order_vnd_snapshot OR OLD.scope_kind_snapshot IS NOT NEW.scope_kind_snapshot OR OLD.booking_cinema_id_snapshot IS NOT NEW.booking_cinema_id_snapshot OR OLD.booking_cinema_code_snapshot IS NOT NEW.booking_cinema_code_snapshot OR OLD.booking_cinema_name_snapshot IS NOT NEW.booking_cinema_name_snapshot OR OLD.eligible_cinemas_snapshot IS NOT NEW.eligible_cinemas_snapshot OR OLD.registered_users_only_snapshot IS NOT NEW.registered_users_only_snapshot OR OLD.first_order_only_snapshot IS NOT NEW.first_order_only_snapshot OR OLD.global_usage_limit_snapshot IS NOT NEW.global_usage_limit_snapshot OR OLD.per_user_usage_limit_snapshot IS NOT NEW.per_user_usage_limit_snapshot OR OLD.applied_discount_vnd IS NOT NEW.applied_discount_vnd OR OLD.gross_before_vnd IS NOT NEW.gross_before_vnd OR OLD.final_after_vnd IS NOT NEW.final_after_vnd OR OLD.reserved_at IS NOT NEW.reserved_at OR OLD.created_at IS NOT NEW.created_at OR (OLD.status <> NEW.status AND NOT (OLD.status = 'reserved' AND NEW.status IN ('redeemed', 'released'))) OR (OLD.status = NEW.status AND (OLD.redeemed_at IS NOT NEW.redeemed_at OR OLD.released_at IS NOT NEW.released_at)) BEGIN SELECT RAISE(ABORT, 'Promotion usage snapshot or transition is immutable'); END");
        DB::unprepared("CREATE TRIGGER booking_promotions_state_update_guard BEFORE UPDATE ON booking_promotions WHEN NOT ((NEW.status = 'reserved' AND NEW.redeemed_at IS NULL AND NEW.released_at IS NULL) OR (NEW.status = 'redeemed' AND NEW.redeemed_at IS NOT NULL AND NEW.released_at IS NULL) OR (NEW.status = 'released' AND NEW.redeemed_at IS NULL AND NEW.released_at IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'Invalid Promotion usage state'); END");
        DB::unprepared("CREATE TRIGGER booking_promotions_delete_guard BEFORE DELETE ON booking_promotions BEGIN SELECT RAISE(ABORT, 'Promotion usage history cannot be deleted'); END");
        DB::unprepared("CREATE TRIGGER promotions_shape_insert_guard BEFORE INSERT ON promotions WHEN NOT (((NEW.type = 'fixed') AND NEW.discount_amount_vnd > 0 AND NEW.discount_percent IS NULL AND NEW.maximum_discount_vnd IS NULL) OR ((NEW.type = 'percentage') AND NEW.discount_amount_vnd IS NULL AND NEW.discount_percent BETWEEN 1 AND 100 AND (NEW.maximum_discount_vnd IS NULL OR NEW.maximum_discount_vnd > 0))) OR (NEW.global_usage_limit IS NOT NULL AND NEW.global_usage_limit <= 0) OR (NEW.per_user_usage_limit IS NOT NULL AND NEW.per_user_usage_limit <= 0) OR (NEW.starts_at IS NOT NULL AND NEW.ends_at IS NOT NULL AND NEW.starts_at >= NEW.ends_at) BEGIN SELECT RAISE(ABORT, 'Invalid Promotion business shape'); END");
        DB::unprepared("CREATE TRIGGER promotions_shape_update_guard BEFORE UPDATE ON promotions WHEN NOT (((NEW.type = 'fixed') AND NEW.discount_amount_vnd > 0 AND NEW.discount_percent IS NULL AND NEW.maximum_discount_vnd IS NULL) OR ((NEW.type = 'percentage') AND NEW.discount_amount_vnd IS NULL AND NEW.discount_percent BETWEEN 1 AND 100 AND (NEW.maximum_discount_vnd IS NULL OR NEW.maximum_discount_vnd > 0))) OR (NEW.global_usage_limit IS NOT NULL AND NEW.global_usage_limit <= 0) OR (NEW.per_user_usage_limit IS NOT NULL AND NEW.per_user_usage_limit <= 0) OR (NEW.starts_at IS NOT NULL AND NEW.ends_at IS NOT NULL AND NEW.starts_at >= NEW.ends_at) BEGIN SELECT RAISE(ABORT, 'Invalid Promotion business shape'); END");
    }

    private function dropGuards(): void
    {
        foreach ([...self::TRIGGERS, 'promotions_shape_insert_guard', 'promotions_shape_update_guard'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
