<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking\Rules;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Stacking\Contracts\StackingRuleInterface;
use AIArmada\Vouchers\Stacking\Enums\StackingRuleType;
use AIArmada\Vouchers\Stacking\StackingDecision;
use Illuminate\Support\Collection;

/**
 * Rule that limits the maximum number of vouchers allowed per cart.
 */
final class MaxVouchersRule implements StackingRuleInterface
{
    private const DEFAULT_MAX = 3;

    private const PRIORITY = 10;

    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $maxVouchers = $config['value'] ?? self::DEFAULT_MAX;

        if ($maxVouchers < 0) {
            return StackingDecision::allow();
        }

        if ($existingVouchers->count() >= $maxVouchers) {
            return StackingDecision::deny(
                reason: "Maximum of {$maxVouchers} voucher(s) allowed per cart",
                conflictsWith: $existingVouchers->first()
            );
        }

        return StackingDecision::allow();
    }

    public function getType(): string
    {
        return StackingRuleType::MaxVouchers->value;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }
}
