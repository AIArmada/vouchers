<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Traits;

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherTransaction;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasVouchers
{
    /**
     * Get the vouchers assigned to this model (Credit System).
     */
    public function assignedVouchers(): BelongsToMany
    {
        return $this->morphToMany(
            Voucher::class,
            'assignee',
            config('vouchers.table_names.voucher_assignments', 'voucher_assignments')
        )
            ->withPivot('assigned_at', 'expires_at')
            ->withTimestamps();
    }

    /**
     * Get the voucher transaction entries for this model (Credit System).
     */
    public function voucherTransactions(): MorphMany
    {
        return $this->morphMany(VoucherTransaction::class, 'walletable');
    }

    /**
     * Get all vouchers in the wallet (Coupon System).
     */
    public function voucherWallets(): MorphMany
    {
        return $this->morphMany(VoucherWallet::class, 'owner');
    }

    /**
     * Get the voucher usages redeemed by this model.
     */
    public function voucherUsages(): MorphMany
    {
        return $this->morphMany(VoucherUsage::class, 'redeemedBy');
    }

    /**
     * Get the wallet balance for a specific voucher (Credit System).
     */
    public function voucherBalance(Voucher $voucher): int
    {
        // We can get the balance from the last transaction or sum amounts.
        // Summing amounts is safer if we don't trust the running balance.
        return (int) $this->voucherTransactions()
            ->where('voucher_id', $voucher->getKey())
            ->sum('amount');
    }

    /**
     * Check if the model can redeem a specific voucher.
     */
    public function canRedeemVoucher(Voucher $voucher, ?int $amount = null): bool
    {
        // Check if assigned
        $isAssigned = $this->assignedVouchers()
            ->where('vouchers.id', $voucher->getKey())
            ->exists();

        if (! $isAssigned) {
            return false;
        }

        // Check if voucher is valid (active, dates, etc.)
        if (! $voucher->canBeRedeemed()) {
            return false;
        }

        // If amount is specified, check balance
        if ($amount !== null) {
            return $this->voucherBalance($voucher) >= $amount;
        }

        return true;
    }

    /**
     * Assign a voucher and optionally grant initial credit.
     */
    public function assignAndCreditVoucher(Voucher $voucher, int $creditAmount = 0, string $description = 'Assignment Gift'): VoucherTransaction
    {
        // Assign if not already assigned
        if (! $this->assignedVouchers()->where('vouchers.id', $voucher->getKey())->exists()) {
            $this->assignedVouchers()->attach($voucher->getKey());
        }

        // Grant credit
        return $this->grantVoucherCredit($voucher, $creditAmount, $description);
    }

    /**
     * Grant credit to the voucher wallet (via transaction).
     */
    public function grantVoucherCredit(Voucher $voucher, int $creditAmount, string $description, ?VoucherWallet $voucherWallet = null): VoucherTransaction
    {
        $balance = $this->voucherBalance($voucher) + $creditAmount;

        return $this->voucherTransactions()->create([
            'voucher_id' => $voucher->getKey(),
            'voucher_wallet_id' => $voucherWallet?->getKey(),
            'amount' => $creditAmount,
            'balance' => $balance,
            'type' => $creditAmount >= 0 ? 'credit' : 'debit',
            'currency' => $voucher->currency,
            'description' => $description,
        ]);
    }

    /**
     * Redeem a voucher, deducting from wallet and recording usage.
     */
    public function redeemVoucher(Voucher $voucher, int $amount): VoucherUsage
    {
        if (! $this->canRedeemVoucher($voucher, $amount)) {
            throw new Exception('Cannot redeem voucher: Insufficient balance or invalid voucher.');
        }

        $walletEntry = $this->resolveVoucherWalletEntry($voucher);

        // 1. Debit wallet
        $this->grantVoucherCredit($voucher, -$amount, 'Redemption', $walletEntry);

        if ($walletEntry) {
            $walletEntry->markAsRedeemed();
        }

        // 2. Record usage
        return VoucherUsage::create([
            'voucher_id' => $voucher->getKey(),
            'discount_amount' => $amount,
            'currency' => $voucher->currency,
            'channel' => 'web', // Default channel, maybe make configurable
            'redeemed_by_type' => $this->getMorphClass(),
            'redeemed_by_id' => $this->getKey(),
            'notes' => 'Redemption via wallet',
            'used_at' => now(),
        ]);
    }

    /**
     * Add a voucher to the wallet (claimed automatically) - Coupon System.
     */
    public function addVoucherToWallet(string $voucherCode): VoucherWallet
    {
        $voucher = Voucher::where('code', $voucherCode)->firstOrFail();

        return $this->voucherWallets()->create([
            'voucher_id' => $voucher->id,
            'is_claimed' => true,
            'claimed_at' => now(),
        ]);
    }

    /**
     * Remove a voucher from the wallet - Coupon System.
     */
    public function removeVoucherFromWallet(string $voucherCode): bool
    {
        $voucher = Voucher::where('code', $voucherCode)->firstOrFail();

        return $this->voucherWallets()
            ->where('voucher_id', $voucher->id)
            ->where('is_redeemed', false)
            ->delete() > 0;
    }

    /**
     * Check if voucher exists in wallet - Coupon System.
     */
    public function hasVoucherInWallet(string $voucherCode): bool
    {
        $voucher = Voucher::where('code', $voucherCode)->first();

        if (! $voucher) {
            return false;
        }

        return $this->voucherWallets()
            ->where('voucher_id', $voucher->id)
            ->exists();
    }

    /**
     * Get all available (usable) vouchers from wallet - Coupon System.
     *
     * @return Collection<int, VoucherWallet>
     */
    public function getAvailableVouchers(): Collection
    {
        return $this->voucherWallets()
            ->with('voucher')
            ->where('is_claimed', true)
            ->where('is_redeemed', false)
            ->get()
            ->filter(fn (VoucherWallet $wallet) => $wallet->canBeUsed());
    }

    /**
     * Get all redeemed vouchers from wallet - Coupon System.
     *
     * @return Collection<int, VoucherWallet>
     */
    public function getRedeemedVouchers(): Collection
    {
        return $this->voucherWallets()
            ->with('voucher')
            ->where('is_redeemed', true)
            ->orderByDesc('redeemed_at')
            ->get();
    }

    /**
     * Get expired vouchers from wallet - Coupon System.
     *
     * @return Collection<int, VoucherWallet>
     */
    public function getExpiredVouchers(): Collection
    {
        return $this->voucherWallets()
            ->with('voucher')
            ->where('is_claimed', true)
            ->where('is_redeemed', false)
            ->get()
            ->filter(fn (VoucherWallet $wallet) => $wallet->isExpired());
    }

    /**
     * Mark a wallet voucher as redeemed - Coupon System.
     */
    public function markVoucherAsRedeemed(string $voucherCode): void
    {
        $voucher = Voucher::where('code', $voucherCode)->firstOrFail();

        $walletEntry = $this->voucherWallets()
            ->where('voucher_id', $voucher->id)
            ->where('is_redeemed', false)
            ->first();

        if ($walletEntry) {
            $walletEntry->markAsRedeemed();
        }
    }

    protected function resolveVoucherWalletEntry(Voucher $voucher, bool $onlyAvailable = true): ?VoucherWallet
    {
        return $this->voucherWallets()
            ->where('voucher_id', $voucher->getKey())
            ->when($onlyAvailable, fn ($query) => $query->where('is_redeemed', false))
            ->orderBy('claimed_at')
            ->first();
    }
}
