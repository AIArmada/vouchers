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
        $tableName = $tables['vouchers'] ?? $prefix . 'vouchers';

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'promotion_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->foreignUuid('promotion_id')->nullable()->after('stacking_priority');
            $table->index('promotion_id');
        });
    }

    public function down(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['vouchers'] ?? $prefix . 'vouchers';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'promotion_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex($table->getTable() . '_promotion_id_index');
            $table->dropColumn('promotion_id');
        });
    }
};
