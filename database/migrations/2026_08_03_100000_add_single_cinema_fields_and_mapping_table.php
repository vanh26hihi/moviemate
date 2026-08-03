<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cinemas', function (Blueprint $table) {
            $table->string('canonical_key')->nullable()->unique()->after('id');
            $table->string('school_name')->nullable()->after('name');
            $table->string('country')->nullable()->after('city');
            $table->boolean('is_primary')->default(false)->index()->after('status');
            $table->timestamp('archived_at')->nullable()->after('is_primary');
            $table->decimal('latitude', 17, 14)->nullable()->change();
            $table->decimal('longitude', 17, 14)->nullable()->change();
        });

        Schema::create('cinema_consolidation_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('original_cinema_id')->nullable();
            $table->unsignedBigInteger('canonical_cinema_id');
            $table->string('original_code')->nullable();
            $table->string('original_name')->nullable();
            $table->string('original_status')->nullable();
            $table->json('original_payload')->nullable();
            $table->timestamp('migrated_at');
            $table->unique(['entity_type', 'entity_id'], 'cinema_mapping_entity_unique');
            $table->index('canonical_cinema_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cinema_consolidation_mappings');

        Schema::table('cinemas', function (Blueprint $table) {
            $table->dropUnique(['canonical_key']);
            $table->dropIndex(['is_primary']);
            $table->dropColumn(['canonical_key', 'school_name', 'country', 'is_primary', 'archived_at']);
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
        });
    }
};
