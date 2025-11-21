<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vouchers.table_names.voucher_transactions', 'voucher_transactions');

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
            $jsonType = (string) commerce_json_column_type('vouchers', 'json');
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();
            $table->index(['voucher_id', 'walletable_type', 'walletable_id']);
            $table->index('voucher_wallet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('vouchers.table_names.voucher_transactions', 'voucher_transactions'));
    }
};
