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
        $tableName = $tables['voucher_assignments'] ?? $prefix . 'voucher_assignments';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('status', 50)->default('active');
            $table->timestampTz('revoked_at')->nullable();
        });
    }
};
