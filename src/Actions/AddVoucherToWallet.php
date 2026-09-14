<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherWallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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

            // Serialize concurrent claims per voucher. The row lock also
            // enforces the one-active-entry rule on drivers without partial
            // unique index support (MySQL).
            $this->voucherQuery()
                ->whereKey($voucher->id)
                ->lockForUpdate()
                ->first();

            // An already-active entry is returned as-is; a redeemed entry
            // does not block a fresh claim.
            $existing = VoucherWallet::where('voucher_id', $voucher->id)
                ->where('holder_type', $holder->getMorphClass())
                ->where('holder_id', $holder->getKey())
                ->whereNull('redeemed_at')
                ->first();

            if ($existing instanceof VoucherWallet) {
                return $existing;
            }

            try {
                return VoucherWallet::create([
                    'voucher_id' => $voucher->id,
                    'holder_type' => $holder->getMorphClass(),
                    'holder_id' => $holder->getKey(),
                    'owner_type' => $voucher->owner_type,
                    'owner_id' => $voucher->owner_id,
                    'claimed_at' => CarbonImmutable::now(),
                    'metadata' => $metadata,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $raced = VoucherWallet::where('voucher_id', $voucher->id)
                    ->where('holder_type', $holder->getMorphClass())
                    ->where('holder_id', $holder->getKey())
                    ->whereNull('redeemed_at')
                    ->first();

                if (! $raced instanceof VoucherWallet) {
                    throw $exception;
                }

                return $raced;
            }
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

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
