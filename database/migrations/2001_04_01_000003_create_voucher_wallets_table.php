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
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['voucher_wallets'] ?? $prefix . 'voucher_wallets';

        Schema::create($tableName, function (Blueprint $table): void {
            $jsonType = commerce_json_column_type('vouchers', 'jsonb');

            $table->uuid('id')->primary();
            $table->foreignUuid('voucher_id');
            $table->nullableUuidMorphs('owner');
            $table->nullableUuidMorphs('holder');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('redeemed_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            // Indexes for common queries
            $table->index('voucher_id'); // For querying wallet entries by voucher
            // Note: nullableUuidMorphs('owner') already creates index on ['owner_type', 'owner_id']
            // Note: nullableUuidMorphs('holder') already creates index on ['holder_type', 'holder_id']
            $table->index('claimed_at'); // For sorting by claim date
            $table->index('redeemed_at'); // For sorting by redemption date
            $table->index(['voucher_id', 'claimed_at', 'redeemed_at'], 'voucher_wallets_available_idx');
        });

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS voucher_wallets_one_active_per_holder ON {$tableName} (voucher_id, holder_type, holder_id) WHERE redeemed_at IS NULL");
    }

    public function down(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['voucher_wallets'] ?? $prefix . 'voucher_wallets';

        Schema::dropIfExists($tableName);
    }
};
