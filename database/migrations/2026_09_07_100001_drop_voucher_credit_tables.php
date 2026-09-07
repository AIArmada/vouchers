<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['voucher_transactions', 'voucher_assignments'] as $tableKey) {
            $tableName = $this->tableName($tableKey);

            if (Schema::hasTable($tableName)) {
                Schema::dropIfExists($tableName);
            }
        }
    }

    private function tableName(string $tableKey): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables[$tableKey] ?? $prefix . $tableKey;
    }
};
