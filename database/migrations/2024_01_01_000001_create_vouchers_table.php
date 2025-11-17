<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vouchers.table_names.vouchers', 'vouchers');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Discount configuration
            $table->string('type'); // percentage, fixed, free_shipping
            $table->bigInteger('value'); // For percentage: store as basis points (e.g., 10.50% = 1050). For fixed: store as cents
            $table->string('currency', 3)->default('MYR');

            // Constraints (in cents)
            $table->bigInteger('min_cart_value')->nullable();
            $table->bigInteger('max_discount')->nullable();

            // Usage limits
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_limit_per_user')->nullable();
            $table->unsignedBigInteger('applied_count')->default(0);
            $table->boolean('allows_manual_redemption')->default(false);

            // Validity period
            $table->datetime('starts_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->string('status')->default('active'); // active, paused, expired, depleted

            // Metadata
            $jsonType = (string) commerce_json_column_type('vouchers', 'json');
            $table->{$jsonType}('target_definition')->nullable();
            $table->{$jsonType}('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('code');
            $table->index('status');
            $table->index(['starts_at', 'expires_at']);
            // Note: nullableUuidMorphs('owner') already creates index on ['owner_type', 'owner_id']
            $table->index('type'); // For filtering by voucher type
            $table->index('expires_at'); // For expiration checks
        });

        // Optional: create GIN indexes when using jsonb on PostgreSQL
        $tableName = config('vouchers.table_names.vouchers', 'vouchers');
        $jsonColumnType = commerce_json_column_type('vouchers', 'json');

        if (
            $jsonColumnType === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX IF NOT EXISTS vouchers_metadata_gin_index ON \"{$tableName}\" USING GIN (\"metadata\")");
            DB::statement("CREATE INDEX IF NOT EXISTS vouchers_target_definition_gin_index ON \"{$tableName}\" USING GIN (\"target_definition\")");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('vouchers.table_names.vouchers', 'vouchers'));
    }
};
