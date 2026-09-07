<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Traits;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasVouchers
{
    /**
     * Get all vouchers in the wallet (Coupon System).
     *
     * @return MorphMany<VoucherWallet, $this>
     */
    public function voucherWallets(): MorphMany
    {
        return $this->morphMany(VoucherWallet::class, 'holder');
    }

    /**
     * Get the voucher usages redeemed by this model.
     *
     * @return MorphMany<VoucherUsage, $this>
     */
    public function voucherUsages(): MorphMany
    {
        return $this->morphMany(VoucherUsage::class, 'redeemedBy');
    }

    /**
     * Add a voucher to the wallet (claimed automatically) - Coupon System.
     */
    public function addVoucherToWallet(string $voucherCode): VoucherWallet
    {
        $voucher = $this->voucherQueryByCode($voucherCode)->firstOrFail();

        /** @var VoucherWallet $voucherWallet */
        $voucherWallet = $this->voucherWallets()->create([
            'voucher_id' => $voucher->id,
            'owner_type' => $voucher->owner_type,
            'owner_id' => $voucher->owner_id,
            'claimed_at' => CarbonImmutable::now(),
        ]);

        return $voucherWallet;
    }

    /**
     * Remove a voucher from the wallet - Coupon System.
     */
    public function removeVoucherFromWallet(string $voucherCode): bool
    {
        $voucher = $this->voucherQueryByCode($voucherCode)->firstOrFail();

        return $this->voucherWallets()
            ->where('voucher_id', $voucher->id)
            ->whereNull('redeemed_at')
            ->delete() > 0;
    }

    /**
     * Check if voucher exists in wallet - Coupon System.
     */
    public function hasVoucherInWallet(string $voucherCode): bool
    {
        $voucher = $this->voucherQueryByCode($voucherCode)->first();

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
        /** @var Collection<int, VoucherWallet> $wallets */
        $wallets = $this->voucherWallets()
            ->with('voucher')
            ->whereNotNull('claimed_at')
            ->whereNull('redeemed_at')
            ->get();

        return $wallets->filter(fn (VoucherWallet $wallet) => $wallet->canBeUsed());
    }

    /**
     * Get all redeemed vouchers from wallet - Coupon System.
     *
     * @return Collection<int, VoucherWallet>
     */
    public function getRedeemedVouchers(): Collection
    {
        /** @var Collection<int, VoucherWallet> $wallets */
        $wallets = $this->voucherWallets()
            ->with('voucher')
            ->whereNotNull('redeemed_at')
            ->orderByDesc('redeemed_at')
            ->get();

        return $wallets;
    }

    /**
     * Get expired vouchers from wallet - Coupon System.
     *
     * @return Collection<int, VoucherWallet>
     */
    public function getExpiredVouchers(): Collection
    {
        /** @var Collection<int, VoucherWallet> $wallets */
        $wallets = $this->voucherWallets()
            ->with('voucher')
            ->whereNotNull('claimed_at')
            ->whereNull('redeemed_at')
            ->get();

        return $wallets->filter(fn (VoucherWallet $wallet) => $wallet->isExpired());
    }

    /**
     * Mark a wallet voucher as redeemed - Coupon System.
     */
    public function markVoucherAsRedeemed(string $voucherCode): void
    {
        $voucher = $this->voucherQueryByCode($voucherCode)->firstOrFail();

        /** @var VoucherWallet|null $walletEntry */
        $walletEntry = $this->voucherWallets()
            ->where('voucher_id', $voucher->id)
            ->whereNull('redeemed_at')
            ->first();

        if ($walletEntry) {
            $walletEntry->markAsRedeemed();
        }
    }

    /**
     * @return Builder<Voucher>
     */
    protected function voucherQueryByCode(string $voucherCode): Builder
    {
        $includeGlobal = (bool) config('vouchers.owner.include_global', false);
        $normalizedCode = $this->normalizeVoucherCode($voucherCode);

        /** @var Builder<Voucher> $query */
        $query = Voucher::query()
            ->forOwner(OwnerContext::resolve(), $includeGlobal)
            ->where('code', $normalizedCode);

        return $query;
    }

    protected function normalizeVoucherCode(string $voucherCode): string
    {
        $normalized = mb_trim($voucherCode);

        if (config('vouchers.code.auto_uppercase', true)) {
            return mb_strtoupper($normalized);
        }

        return $normalized;
    }
}
