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
        $tableName = $tables['voucher_assignments'] ?? $prefix . 'voucher_assignments';

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('voucher_id');
            $table->uuidMorphs('assignee'); // User, etc.
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $jsonType = (string) config('vouchers.database.json_column_type', commerce_json_column_type('vouchers', 'json'));
            $table->{$jsonType}('metadata')->nullable();
            $table->unique(['voucher_id', 'assignee_type', 'assignee_id'], 'voucher_assignee_unique');
            $table->index('voucher_id');
            $table->index(['voucher_id', 'assigned_at']);
            $table->index('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['voucher_assignments'] ?? $prefix . 'voucher_assignments';

        Schema::dropIfExists($tableName);
    }
};
