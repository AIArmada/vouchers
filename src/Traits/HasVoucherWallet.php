<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Traits;

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasVoucherWallet
{
    /**
     * Get all vouchers in the wallet.
     */
    public function voucherWallets(): MorphMany
    {
        return $this->morphMany(VoucherWallet::class, 'owner');
    }

    /**
     * Add a voucher to the wallet (claimed automatically).
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
     * Remove a voucher from the wallet.
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
     * Check if voucher exists in wallet.
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
     * Get all available (usable) vouchers from wallet.
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
     * Get all redeemed vouchers from wallet.
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
     * Get expired vouchers from wallet.
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
     * Mark a wallet voucher as redeemed.
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
}
