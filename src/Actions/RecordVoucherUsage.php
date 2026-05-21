<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Exceptions\VoucherUsageLimitException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\States\Depleted;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Record usage of a voucher.
 */
final class RecordVoucherUsage
{
    use AsAction;
    use QueriesVouchers;

    /**
     * Record a voucher usage.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null,
        ?VoucherModel $voucherModel = null
    ): VoucherUsage {
        return DB::transaction(function () use ($code, $discountAmount, $channel, $metadata, $redeemedBy, $notes, $voucherModel): VoucherUsage {
            $voucher = $this->resolveVoucher($code, $voucherModel);

            /** @var VoucherModel|null $lockedVoucher */
            $lockedVoucher = $this->voucherQuery()
                ->whereKey($voucher->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedVoucher) {
                throw new VoucherNotFoundException("Voucher with code '{$code}' not found.");
            }

            // Check global usage limit
            if ($lockedVoucher->usage_limit !== null) {
                $currentUses = VoucherUsage::where('voucher_id', $lockedVoucher->id)->count();
                if ($currentUses >= $lockedVoucher->usage_limit) {
                    throw VoucherUsageLimitException::globalLimit($lockedVoucher->code);
                }
            }

            // Check per-user usage limit
            if ($redeemedBy !== null && $lockedVoucher->usage_limit_per_user) {
                $currentUserUses = VoucherUsage::where('voucher_id', $lockedVoucher->id)
                    ->where('redeemed_by_type', $redeemedBy->getMorphClass())
                    ->where('redeemed_by_id', $redeemedBy->getKey())
                    ->count();

                if ($currentUserUses >= $lockedVoucher->usage_limit_per_user) {
                    throw VoucherUsageLimitException::userLimit($lockedVoucher->code);
                }
            }

            $usage = VoucherUsage::create([
                'voucher_id' => $lockedVoucher->id,
                'discount_amount' => $discountAmount->getAmount(),
                'currency' => $discountAmount->getCurrency()->getCurrency(),
                'channel' => $channel ?? 'web',
                'metadata' => $metadata,
                'target_definition' => $lockedVoucher->target_definition,
                'redeemed_by_type' => $redeemedBy?->getMorphClass(),
                'redeemed_by_id' => $redeemedBy?->getKey(),
                'notes' => $notes,
                'used_at' => now(),
            ]);

            // Update status to depleted if usage limit reached
            // Note: applied_count tracks cart applications (incremented by IncrementVoucherAppliedCount
            // listener on VoucherApplied event). Do NOT increment it here — that would double-count
            // any voucher that is both applied to a cart and then redeemed, corrupting conversion
            // rate and abandoned count statistics.
            if ($lockedVoucher->usage_limit !== null) {
                $newUsageCount = VoucherUsage::where('voucher_id', $lockedVoucher->id)->count();
                if ($newUsageCount >= $lockedVoucher->usage_limit) {
                    $lockedVoucher->update(['status' => Depleted::class]);
                }
            }

            return $usage;
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

    private function resolveVoucher(string $code, ?VoucherModel $voucherModel): VoucherModel
    {
        if ($voucherModel === null) {
            return $this->findVoucher($code);
        }

        $voucher = $this->voucherQuery()
            ->whereKey($voucherModel->getKey())
            ->first();

        if (! $voucher) {
            throw new VoucherNotFoundException("Voucher with code '{$code}' not found.");
        }

        return $voucher;
    }
}
