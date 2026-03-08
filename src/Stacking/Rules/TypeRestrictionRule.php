<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking\Rules;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Stacking\Contracts\StackingRuleInterface;
use AIArmada\Vouchers\Stacking\Enums\StackingRuleType;
use AIArmada\Vouchers\Stacking\StackingDecision;
use Illuminate\Support\Collection;

/**
 * Rule that limits how many vouchers of each type can be applied.
 *
 * Example: Only allow 1 percentage voucher and 2 fixed vouchers.
 */
final class TypeRestrictionRule implements StackingRuleInterface
{
    private const PRIORITY = 40;

    /**
     * @var array<string, int>
     */
    private const DEFAULT_MAX_PER_TYPE = [
        'percentage' => 1,
        'fixed' => 2,
        'free_shipping' => 1,
    ];

    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $maxPerType = $config['max_per_type'] ?? self::DEFAULT_MAX_PER_TYPE;

        $newVoucherType = $this->getVoucherType($newVoucher);
        $maxForType = $maxPerType[$newVoucherType] ?? PHP_INT_MAX;

        $currentCountForType = $existingVouchers
            ->filter(fn (VoucherCondition $v): bool => $this->getVoucherType($v) === $newVoucherType)
            ->count();

        if ($currentCountForType >= $maxForType) {
            $typeLabel = $this->getTypeLabel($newVoucherType);

            return StackingDecision::deny(
                reason: "Maximum of {$maxForType} {$typeLabel} voucher(s) allowed",
                conflictsWith: $existingVouchers
                    ->first(fn (VoucherCondition $v): bool => $this->getVoucherType($v) === $newVoucherType)
            );
        }

        return StackingDecision::allow();
    }

    public function getType(): string
    {
        return StackingRuleType::TypeRestriction->value;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    private function getVoucherType(VoucherCondition $voucher): string
    {
        $voucherData = $voucher->getVoucher();

        return $voucherData->type->value;
    }

    private function getTypeLabel(string $type): string
    {
        return match ($type) {
            VoucherType::Percentage->value => 'percentage',
            VoucherType::Fixed->value => 'fixed amount',
            VoucherType::FreeShipping->value => 'free shipping',
            default => $type,
        };
    }
}
