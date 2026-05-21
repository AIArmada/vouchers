<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $jsonType = (string) config('vouchers.database.json_column_type', commerce_json_column_type('vouchers', 'json'));

            $table->uuid('id')->primary();
            $table->foreignUuid('voucher_id');
            $table->nullableUuidMorphs('owner');
            $table->nullableUuidMorphs('holder');
            $table->boolean('is_claimed')->default(false);
            $table->timestamp('claimed_at')->nullable();
            $table->boolean('is_redeemed')->default(false);
            $table->timestamp('redeemed_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('voucher_id'); // For querying wallet entries by voucher
            // Note: nullableUuidMorphs('owner') already creates index on ['owner_type', 'owner_id']
            // Note: nullableUuidMorphs('holder') already creates index on ['holder_type', 'holder_id']
            $table->index('is_claimed'); // For filtering claimed status
            $table->index('is_redeemed'); // For filtering redeemed status
            $table->index(['is_redeemed', 'is_claimed']); // For available vouchers queries
            $table->index('claimed_at'); // For sorting by claim date
            $table->index('redeemed_at'); // For sorting by redemption date
            $table->index(['voucher_id', 'is_claimed', 'is_redeemed'], 'voucher_wallets_available_idx');

            // Unique constraint: one voucher per holder (only for non-redeemed entries)
            $table->unique(['voucher_id', 'holder_type', 'holder_id', 'is_redeemed']);
        });
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
