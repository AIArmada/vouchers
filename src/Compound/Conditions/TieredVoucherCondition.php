<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Conditions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;

/**
 * Tiered discount voucher condition.
 *
 * Applies different discount levels based on cart value thresholds.
 *
 * Configuration example:
 * {
 *   "tiers": [
 *     { "min_value": 10000, "discount": "-5%", "label": "Bronze" },
 *     { "min_value": 20000, "discount": "-10%", "label": "Silver" },
 *     { "min_value": 50000, "discount": "-15%", "label": "Gold" }
 *   ],
 *   "calculation_base": "subtotal",
 *   "apply_highest_only": true
 * }
 */
class TieredVoucherCondition extends CompoundVoucherCondition
{
    public function calculateDiscount(Cart $cart): int
    {
        $tier = $this->getApplicableTier($cart);

        if ($tier === null) {
            return 0;
        }

        $baseValue = $this->getCalculationBaseValue($cart);
        $discount = $tier['discount'] ?? '0';

        return $this->calculateDiscountAmount($baseValue, $discount);
    }

    public function getDiscountDescription(Cart $cart): string
    {
        $tier = $this->getApplicableTier($cart);

        if ($tier === null) {
            $tiers = $this->getTiers();
            if (! empty($tiers)) {
                $minTier = $tiers[0];
                $minValue = ($minTier['min_value'] ?? 0) / 100;

                return "Spend RM{$minValue}+ to unlock discounts";
            }

            return 'Tiered discount';
        }

        $label = $tier['label'] ?? 'Tier';
        $discount = $tier['discount'] ?? '0';

        if (str_ends_with($discount, '%')) {
            $percent = abs((int) str_replace(['%', '-', '+'], '', $discount));

            return "{$label}: {$percent}% off";
        }

        $amount = abs((int) str_replace(['-', '+'], '', $discount)) / 100;

        return "{$label}: RM{$amount} off";
    }

    public function meetsRequirements(Cart $cart): bool
    {
        return $this->getApplicableTier($cart) !== null;
    }

    /**
     * Get the applicable tier for the cart.
     *
     * @return array<string, mixed>|null
     */
    public function getApplicableTier(Cart $cart): ?array
    {
        $tiers = $this->getTiers();
        $baseValue = $this->getCalculationBaseValue($cart);
        $applyHighestOnly = (bool) $this->getConfig('apply_highest_only', true);

        // Sort tiers by min_value descending to find highest applicable
        usort($tiers, fn (array $a, array $b): int => ($b['min_value'] ?? 0) <=> ($a['min_value'] ?? 0));

        foreach ($tiers as $tier) {
            $minValue = $tier['min_value'] ?? 0;

            if ($baseValue >= $minValue) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Get all available tiers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTiers(): array
    {
        $tiers = $this->getConfig('tiers', []);

        if (! is_array($tiers)) {
            return [];
        }

        return $tiers;
    }

    /**
     * Get the next tier that could be unlocked.
     *
     * @return array<string, mixed>|null
     */
    public function getNextTier(Cart $cart): ?array
    {
        $tiers = $this->getTiers();
        $baseValue = $this->getCalculationBaseValue($cart);

        // Sort tiers by min_value ascending
        usort($tiers, fn (array $a, array $b): int => ($a['min_value'] ?? 0) <=> ($b['min_value'] ?? 0));

        foreach ($tiers as $tier) {
            $minValue = $tier['min_value'] ?? 0;

            if ($baseValue < $minValue) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Get the amount needed to unlock the next tier.
     */
    public function getAmountToNextTier(Cart $cart): int
    {
        $nextTier = $this->getNextTier($cart);

        if ($nextTier === null) {
            return 0;
        }

        $baseValue = $this->getCalculationBaseValue($cart);
        $minValue = $nextTier['min_value'] ?? 0;

        return max(0, $minValue - $baseValue);
    }

    /**
     * Get the calculation base value from the cart.
     */
    protected function getCalculationBaseValue(Cart $cart): int
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
     * Calculate discount amount from a discount string.
     *
     * @param  int  $baseValue  Base value in cents
     * @param  string  $discount  Discount string (e.g., "-10%", "-500")
     * @return int Discount amount in cents
     */
    protected function calculateDiscountAmount(int $baseValue, string $discount): int
    {
        $discount = mb_trim($discount);

        if (str_ends_with($discount, '%')) {
            // Percentage discount
            $percent = abs((float) str_replace(['%', '-', '+'], '', $discount));

            return (int) round($baseValue * ($percent / 100));
        }

        // Fixed discount
        $amount = abs((int) str_replace(['-', '+'], '', $discount));

        return min($amount, $baseValue);
    }

    protected function getConditionPhase(): ConditionPhase
    {
        return ConditionPhase::CART_SUBTOTAL;
    }

    protected function getConditionValue(): string
    {
        // Tiered discounts need to be calculated dynamically
        return '-0';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConditionAttributes(): array
    {
        return array_merge(parent::getConditionAttributes(), [
            'tiers' => $this->getTiers(),
            'calculation_base' => $this->getConfig('calculation_base', 'subtotal'),
        ]);
    }
}
