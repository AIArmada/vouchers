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
 * Rule that requires minimum cart value for voucher stacking.
 */
final class ValueThresholdRule implements StackingRuleInterface
{
    private const PRIORITY = 5;

    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $minimumValue = $config['minimum'] ?? null;

        if ($minimumValue === null || $existingVouchers->isEmpty()) {
            return StackingDecision::allow();
        }

        $cartSubtotal = $this->getCartSubtotal($cart);

        if ($cartSubtotal < $minimumValue) {
            $formattedMin = $this->formatMoney($minimumValue, $cart);

            return StackingDecision::deny(
                reason: "Cart value must be at least {$formattedMin} to stack vouchers"
            );
        }

        return StackingDecision::allow();
    }

    public function getType(): string
    {
        return StackingRuleType::ValueThreshold->value;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    private function getCartSubtotal(Cart $cart): int
    {
        $subtotal = $cart->subtotal();

        return (int) $subtotal->getAmount();
    }

    private function formatMoney(int $amountInCents, Cart $cart): string
    {
        $currency = config('vouchers.default_currency', 'MYR');

        return (string) Money::{$currency}($amountInCents);
    }
}
