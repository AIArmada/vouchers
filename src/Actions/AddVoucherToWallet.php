<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Add a voucher to the owner's wallet.
 */
final class AddVoucherToWallet
{
    use AsAction;
    use QueriesVouchers;

    /**
     * Add a voucher to the owner's wallet.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(string $code, Model $holder, ?array $metadata = null): VoucherWallet
    {
        return DB::transaction(function () use ($code, $holder, $metadata): VoucherWallet {
            $voucher = $this->findVoucher($code);

            // Check if already in wallet
            $existing = VoucherWallet::where('voucher_id', $voucher->id)
                ->where('holder_type', $holder->getMorphClass())
                ->where('holder_id', $holder->getKey())
                ->first();

            if ($existing) {
                return $existing;
            }

            return VoucherWallet::create([
                'voucher_id' => $voucher->id,
                'holder_type' => $holder->getMorphClass(),
                'holder_id' => $holder->getKey(),
                'owner_type' => $voucher->owner_type,
                'owner_id' => $voucher->owner_id,
                'is_claimed' => true,
                'claimed_at' => now(),
                'metadata' => $metadata,
            ]);
        });
    }

    private function findVoucher(string $code): VoucherModel
    {
        $normalizedCode = $this->normalizeCode($code);

        $voucher = $this->voucherQuery()
            ->where('code', $normalizedCode)
            ->first();

        if (! $voucher) {
            throw new VoucherNotFoundException("Voucher with code '{$code}' not found.");
        }

        return $voucher;
    }
}
