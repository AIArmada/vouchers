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
        Schema::create(config('vouchers.table_names.vouchers', 'vouchers'), function (Blueprint $table): void {
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
            $table->integer('times_used')->default(0);
            $table->boolean('allows_manual_redemption')->default(false);

            // Validity period
            $table->datetime('starts_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->string('status')->default('active'); // active, paused, expired, depleted

            // Targeting (portable by default, overridable via config)
            $jsonType = (string) commerce_json_column_type('vouchers', 'json');
            $table->{$jsonType}('applicable_products')->nullable();
            $table->{$jsonType}('excluded_products')->nullable();
            $table->{$jsonType}('applicable_categories')->nullable();

            // Metadata
            $table->{$jsonType}('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index('status');
            $table->index(['starts_at', 'expires_at']);
        });

        // Optional: create GIN indexes when using jsonb on PostgreSQL
        $tableName = config('vouchers.table_names.vouchers', 'vouchers');
        if (
            commerce_json_column_type('vouchers', 'json') === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX IF NOT EXISTS vouchers_applicable_products_gin_index ON \"{$tableName}\" USING GIN (\"applicable_products\")");
            DB::statement("CREATE INDEX IF NOT EXISTS vouchers_excluded_products_gin_index ON \"{$tableName}\" USING GIN (\"excluded_products\")");
            DB::statement("CREATE INDEX IF NOT EXISTS vouchers_applicable_categories_gin_index ON \"{$tableName}\" USING GIN (\"applicable_categories\")");
            DB::statement("CREATE INDEX IF NOT EXISTS vouchers_metadata_gin_index ON \"{$tableName}\" USING GIN (\"metadata\")");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('vouchers.table_names.vouchers', 'vouchers'));
    }
};
