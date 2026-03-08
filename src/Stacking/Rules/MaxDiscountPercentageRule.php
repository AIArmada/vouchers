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
 * Rule that limits total discount as a percentage of cart value.
 */
final class MaxDiscountPercentageRule implements StackingRuleInterface
{
    private const DEFAULT_MAX_PERCENTAGE = 50;

    private const PRIORITY = 25;

    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $maxPercentage = $config['value'] ?? self::DEFAULT_MAX_PERCENTAGE;

        if ($maxPercentage <= 0 || $maxPercentage > 100) {
            return StackingDecision::allow();
        }

        $cartSubtotal = $this->getCartSubtotal($cart);

        if ($cartSubtotal <= 0) {
            return StackingDecision::allow();
        }

        $currentDiscount = $existingVouchers->sum(
            fn (VoucherCondition $v): float => abs($v->getCalculatedValue($cartSubtotal))
        );

        $newDiscount = abs($newVoucher->getCalculatedValue($cartSubtotal));
        $totalDiscount = $currentDiscount + $newDiscount;

        $discountPercentage = ($totalDiscount / $cartSubtotal) * 100;

        if ($discountPercentage > $maxPercentage) {
            return StackingDecision::deny(
                reason: "Total discount cannot exceed {$maxPercentage}% of cart value"
            );
        }

        return StackingDecision::allow();
    }

    public function getType(): string
    {
        return StackingRuleType::MaxDiscountPercentage->value;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    private function getCartSubtotal(Cart $cart): float
    {
        $subtotal = $cart->subtotal();

        return (float) $subtotal->getAmount();
    }
}
