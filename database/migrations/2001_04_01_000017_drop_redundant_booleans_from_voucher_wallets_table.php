<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('vouchers.database.tables', []);
        $prefix = config('vouchers.database.table_prefix', '');
        $tableName = $tables['voucher_wallets'] ?? $prefix . 'voucher_wallets';

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $table->dropIndex(['is_claimed']);
            $table->dropIndex(['is_redeemed']);
            $table->dropIndex(['is_redeemed', 'is_claimed']);
            $table->dropIndex('voucher_wallets_available_idx');

            $table->dropUnique("{$tableName}_voucher_id_holder_type_holder_id_is_redeemed_unique");
        });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('is_claimed');
            $table->dropColumn('is_redeemed');
        });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->index(['voucher_id', 'claimed_at', 'redeemed_at'], 'voucher_wallets_available_idx');
        });

        Schema::table($tableName, function (Blueprint $table): void {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS voucher_wallets_one_active_per_holder ON {$tableName} (voucher_id, holder_type, holder_id) WHERE redeemed_at IS NULL");
        });
    }
};
