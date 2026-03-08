<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking\Rules;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Stacking\Contracts\StackingRuleInterface;
use AIArmada\Vouchers\Stacking\Enums\StackingRuleType;
use AIArmada\Vouchers\Stacking\StackingDecision;
use Akaunting\Money\Money;
use Illuminate\Support\Collection;

/**
 * Rule that limits the maximum absolute discount amount.
 */
final class MaxDiscountRule implements StackingRuleInterface
{
    private const PRIORITY = 20;

    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $maxDiscount = $config['value'] ?? null;

        if ($maxDiscount === null) {
            return StackingDecision::allow();
        }

        $cartSubtotal = $this->getCartSubtotal($cart);

        $currentDiscount = $existingVouchers->sum(
            fn (VoucherCondition $v): float => abs($v->getCalculatedValue($cartSubtotal))
        );

        $newDiscount = abs($newVoucher->getCalculatedValue($cartSubtotal));
        $totalDiscount = $currentDiscount + $newDiscount;

        if ($totalDiscount > $maxDiscount) {
            $formattedMax = $this->formatMoney($maxDiscount, $cart);

            return StackingDecision::deny(
                reason: "Total discount would exceed maximum of {$formattedMax}"
            );
        }

        return StackingDecision::allow();
    }

    public function getType(): string
    {
        return StackingRuleType::MaxDiscount->value;
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

    private function formatMoney(int $amountInCents, Cart $cart): string
    {
        $currency = config('vouchers.default_currency', 'MYR');

        return (string) Money::{$currency}($amountInCents);
    }
}
