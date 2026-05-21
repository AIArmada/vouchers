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
        $tableName = $tables['voucher_transactions'] ?? $prefix . 'voucher_transactions';

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('voucher_id');
            $table->foreignUuid('voucher_wallet_id')->nullable();
            $table->uuidMorphs('walletable'); // User, etc.
            $table->integer('amount'); // cents, positive or negative
            $table->integer('balance'); // running balance
            $table->string('type'); // credit, debit
            $table->string('currency', 3);
            $table->string('description')->nullable();
            $jsonType = (string) config('vouchers.database.json_column_type', commerce_json_column_type('vouchers', 'json'));
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();
            $table->index(['voucher_id', 'walletable_type', 'walletable_id']);
            $table->index('voucher_wallet_id');
            $table->index('type');
            $table->index('currency');
            $table->index('created_at');
            $table->index(['voucher_id', 'type'], 'voucher_transactions_voucher_type_idx');
        });
    }

    public function down(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['voucher_transactions'] ?? $prefix . 'voucher_transactions';

        Schema::dropIfExists($tableName);
    }
};
