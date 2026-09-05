<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vouchers.database.tables.voucher_usage', 'voucher_usage');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'idempotency_key')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('idempotency_key', 64)->nullable()->after('voucher_id');
            });
        }

        if (! Schema::hasIndex($tableName, 'voucher_usage_idempotency_unique')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique(['voucher_id', 'idempotency_key'], 'voucher_usage_idempotency_unique');
            });
        }
    }
};
