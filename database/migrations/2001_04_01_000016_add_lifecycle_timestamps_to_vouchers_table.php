<?php

declare(strict_types=1);

use AIArmada\Vouchers\States\Active;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('vouchers.database.tables', []);
        $prefix = config('vouchers.database.table_prefix', '');
        $tableName = $tables['vouchers'] ?? $prefix . 'vouchers';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('paused_at')->nullable();
            $table->timestampTz('depleted_at')->nullable();
            $table->timestampTz('last_activated_at')->nullable();
        });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->index('paused_at');
            $table->index('depleted_at');
            $table->index('last_activated_at');
        });

        $fqcnDefault = addslashes(Active::class);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN status DROP DEFAULT");
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN status SET DEFAULT '{$fqcnDefault}'");
        }

        DB::statement("UPDATE {$tableName} SET status = '{$fqcnDefault}' WHERE status = 'active'");
    }
};
