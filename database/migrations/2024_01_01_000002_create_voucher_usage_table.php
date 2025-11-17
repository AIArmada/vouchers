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
        Schema::create(config('vouchers.table_names.voucher_usage', 'voucher_usage'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('voucher_id')->constrained(
                config('vouchers.table_names.vouchers', 'vouchers')
            )->cascadeOnDelete();
            $table->bigInteger('discount_amount'); // stored in cents
            $table->string('currency', 3);
            $table->string('channel')->nullable();
            $table->nullableUuidMorphs('redeemed_by');
            $table->text('notes')->nullable();
            $jsonType = (string) commerce_json_column_type('vouchers', 'json');
            $table->{$jsonType}('target_definition')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamp('used_at');

            // Indexes
            $table->index('voucher_id'); // For querying usage by voucher
            // Note: nullableUuidMorphs('redeemed_by') already creates index on ['redeemed_by_type', 'redeemed_by_id']
            $table->index('channel'); // For filtering by redemption channel
            $table->index('used_at'); // For sorting by usage date
            $table->index(['voucher_id', 'used_at']); // For voucher usage history
        });

        // Optional: create GIN index when using jsonb on PostgreSQL
        $tableName = config('vouchers.table_names.voucher_usage', 'voucher_usage');
        $jsonColumnType = commerce_json_column_type('vouchers', 'json');

        if (
            $jsonColumnType === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX IF NOT EXISTS voucher_usage_metadata_gin_index ON \"{$tableName}\" USING GIN (\"metadata\")");
            DB::statement("CREATE INDEX IF NOT EXISTS voucher_usage_target_definition_gin_index ON \"{$tableName}\" USING GIN (\"target_definition\")");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('vouchers.table_names.voucher_usage', 'voucher_usage'));
    }
};
