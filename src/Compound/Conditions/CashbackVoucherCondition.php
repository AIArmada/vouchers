<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Conditions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;

/**
 * Cashback voucher condition.
 *
 * Unlike other vouchers, cashback doesn't reduce cart total at checkout.
 * Instead, it credits the user's wallet after order completion.
 *
 * Configuration example:
 * {
 *   "rate": 500,
 *   "rate_type": "percentage",
 *   "max_cashback": 5000,
 *   "min_order_value": 10000,
 *   "credit_to": "wallet",
 *   "credit_delay_hours": 168,
 *   "requires_order_completion": true
 * }
 */
class CashbackVoucherCondition extends CompoundVoucherCondition
{
    public function calculateDiscount(Cart $cart): int
    {
        // Cashback doesn't reduce cart total at checkout
        return 0;
    }

    /**
     * Calculate the cashback amount that will be credited.
     */
    public function calculateCashback(Cart $cart): int
    {
        if (! $this->meetsRequirements($cart)) {
            return 0;
        }

        $rate = (int) $this->getConfig('rate', 0);
        $rateType = $this->getConfig('rate_type', 'percentage');
        $maxCashback = $this->getConfig('max_cashback');
        $baseValue = $this->getCashbackBaseValue($cart);

        $cashback = match ($rateType) {
            'percentage' => $this->calculatePercentageCashback($baseValue, $rate),
            'fixed' => $rate,
            'per_item' => $this->calculatePerItemCashback($cart, $rate),
            default => $this->calculatePercentageCashback($baseValue, $rate),
        };

        if ($maxCashback !== null) {
            $cashback = min($cashback, (int) $maxCashback);
        }

        return $cashback;
    }

    public function getDiscountDescription(Cart $cart): string
    {
        $rate = (int) $this->getConfig('rate', 0);
        $rateType = $this->getConfig('rate_type', 'percentage');
        $creditTo = $this->getConfig('credit_to', 'wallet');

        $destination = match ($creditTo) {
            'wallet' => 'wallet',
            'next_order' => 'next order',
            'points' => 'points',
            default => 'wallet',
        };

        if ($rateType === 'percentage') {
            // Rate is in basis points (500 = 5%)
            $percent = $rate / 100;

            return "{$percent}% cashback to {$destination}";
        }

        if ($rateType === 'fixed') {
            $amount = $rate / 100;

            return "RM{$amount} cashback to {$destination}";
        }

        if ($rateType === 'per_item') {
            $amount = $rate / 100;

            return "RM{$amount} cashback per item to {$destination}";
        }

        return "Cashback to {$destination}";
    }

    public function meetsRequirements(Cart $cart): bool
    {
        $minOrderValue = $this->getConfig('min_order_value');

        if ($minOrderValue !== null) {
            $cartValue = $cart->getRawSubtotal();
            if ($cartValue < (int) $minOrderValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the credit destination.
     */
    public function getCreditDestination(): string
    {
        return (string) $this->getConfig('credit_to', 'wallet');
    }

    /**
     * Get the credit delay in hours.
     */
    public function getCreditDelayHours(): int
    {
        return (int) $this->getConfig('credit_delay_hours', 0);
    }

    /**
     * Check if order completion is required before crediting.
     */
    public function requiresOrderCompletion(): bool
    {
        return (bool) $this->getConfig('requires_order_completion', true);
    }

    /**
     * Get the minimum order value required.
     */
    public function getMinOrderValue(): ?int
    {
        $value = $this->getConfig('min_order_value');

        return $value !== null ? (int) $value : null;
    }

    /**
     * Get the maximum cashback amount.
     */
    public function getMaxCashback(): ?int
    {
        $value = $this->getConfig('max_cashback');

        return $value !== null ? (int) $value : null;
    }

    /**
     * Get cashback configuration for processing after checkout.
     *
     * @return array<string, mixed>
     */
    public function getCashbackConfig(Cart $cart): array
    {
        return [
            'amount' => $this->calculateCashback($cart),
            'credit_to' => $this->getCreditDestination(),
            'credit_delay_hours' => $this->getCreditDelayHours(),
            'requires_order_completion' => $this->requiresOrderCompletion(),
            'voucher_id' => $this->voucher->id,
            'voucher_code' => $this->voucher->code,
        ];
    }

    /**
     * Get the base value for cashback calculation.
     */
    protected function getCashbackBaseValue(Cart $cart): int
    {
        $base = $this->getConfig('calculation_base', 'subtotal');

        return match ($base) {
            'subtotal' => $cart->getRawSubtotal(),
            'total' => $cart->getRawTotal(),
            'items_total' => $cart->getRawSubtotalWithoutConditions(),
            default => $cart->getRawSubtotal(),
        };
    }

    /**
     * Calculate percentage-based cashback.
     *
     * @param  int  $baseValue  Base value in cents
     * @param  int  $rate  Rate in basis points (500 = 5%)
     */
    protected function calculatePercentageCashback(int $baseValue, int $rate): int
    {
        return (int) round($baseValue * ($rate / 10000));
    }

    /**
     * Calculate per-item cashback.
     *
     * @param  int  $rate  Rate per item in cents
     */
    protected function calculatePerItemCashback(Cart $cart, int $rate): int
    {
        $totalItems = 0;

        foreach ($cart->getItems() as $item) {
            $totalItems += $item->quantity;
        }

        return $totalItems * $rate;
    }

    protected function getConditionPhase(): ConditionPhase
    {
        // Cashback doesn't affect cart totals directly
        return ConditionPhase::GRAND_TOTAL;
    }

    protected function getConditionValue(): string
    {
        // No discount at checkout - just tracking
        return '+0';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConditionAttributes(): array
    {
        return array_merge(parent::getConditionAttributes(), [
            'rate' => $this->getConfig('rate', 0),
            'rate_type' => $this->getConfig('rate_type', 'percentage'),
            'credit_to' => $this->getCreditDestination(),
            'credit_delay_hours' => $this->getCreditDelayHours(),
            'requires_order_completion' => $this->requiresOrderCompletion(),
            'is_cashback' => true,
        ]);
    }
}
