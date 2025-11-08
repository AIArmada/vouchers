<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('vouchers.table_names.voucher_wallets', 'voucher_wallets'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id');
            $table->uuidMorphs('owner');
            $table->boolean('is_claimed')->default(false);
            $table->timestamp('claimed_at')->nullable();
            $table->boolean('is_redeemed')->default(false);
            $table->timestamp('redeemed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('voucher_id')
                ->references('id')
                ->on(config('vouchers.table_names.vouchers', 'vouchers'))
                ->cascadeOnDelete();

            // Indexes for common queries
            $table->index('voucher_id'); // For querying wallet entries by voucher
            // Note: uuidMorphs('owner') already creates index on ['owner_type', 'owner_id']
            $table->index('is_claimed'); // For filtering claimed status
            $table->index('is_redeemed'); // For filtering redeemed status
            $table->index(['is_redeemed', 'is_claimed']); // For available vouchers queries
            $table->index('claimed_at'); // For sorting by claim date
            $table->index('redeemed_at'); // For sorting by redemption date

            // Unique constraint: one voucher per owner (only for non-redeemed entries)
            $table->unique(['voucher_id', 'owner_type', 'owner_id', 'is_redeemed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('vouchers.table_names.voucher_wallets', 'voucher_wallets'));
    }
};
