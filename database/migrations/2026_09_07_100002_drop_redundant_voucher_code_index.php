<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = $this->vouchersTable();

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $indexes = Schema::getIndexes($tableName);
        $hasUniqueCodeIndex = false;

        foreach ($indexes as $index) {
            if (($index['columns'] ?? null) === ['code'] && ($index['unique'] ?? false)) {
                $hasUniqueCodeIndex = true;

                break;
            }
        }

        if (! $hasUniqueCodeIndex) {
            return;
        }

        foreach ($indexes as $index) {
            if (($index['columns'] ?? null) !== ['code']
                || ($index['unique'] ?? false)
                || ($index['primary'] ?? false)) {
                continue;
            }

            $indexName = $index['name'] ?? null;

            if (! is_string($indexName) || $indexName === '') {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        }
    }

    private function vouchersTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['vouchers'] ?? $prefix . 'vouchers';
    }
};
