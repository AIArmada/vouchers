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
            $voucher = $voucherModel ?? $this->findVoucher($code);

            /** @var VoucherModel $lockedVoucher */
            $lockedVoucher = VoucherModel::query()
                ->whereKey($voucher->id)
                ->lockForUpdate()
                ->firstOrFail();

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

            // Update the voucher use count
            $lockedVoucher->increment('applied_count');

            // Update status to depleted if usage limit reached
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
}
