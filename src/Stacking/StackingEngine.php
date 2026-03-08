<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Stacking\Contracts\StackingPolicyInterface;
use AIArmada\Vouchers\Stacking\Contracts\StackingRuleInterface;
use AIArmada\Vouchers\Stacking\Enums\StackingMode;
use AIArmada\Vouchers\Stacking\Rules\CampaignExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\CategoryExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\MaxDiscountPercentageRule;
use AIArmada\Vouchers\Stacking\Rules\MaxDiscountRule;
use AIArmada\Vouchers\Stacking\Rules\MaxVouchersRule;
use AIArmada\Vouchers\Stacking\Rules\MutualExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\TypeRestrictionRule;
use AIArmada\Vouchers\Stacking\Rules\ValueThresholdRule;
use Illuminate\Support\Collection;

/**
 * Orchestrates stacking rule evaluation and voucher combination optimization.
 */
class StackingEngine
{
    /**
     * Maximum vouchers allowed for best-deal combination calculation.
     * Higher values cause exponential time complexity O(2^n).
     */
    private const MAX_VOUCHERS_FOR_BEST_DEAL = 10;

    /**
     * Maximum combinations to evaluate before falling back to priority-based.
     * Prevents runaway computation with many vouchers.
     */
    private const MAX_COMBINATIONS_TO_EVALUATE = 500;

    /**
     * @var array<string, StackingRuleInterface>
     */
    private array $rules = [];

    public function __construct(
        private StackingPolicyInterface $policy
    ) {
        $this->registerDefaultRules();
    }

    /**
     * Check if a voucher can be added given existing vouchers.
     *
     * @param  Collection<int, VoucherCondition>  $existing
     */
    public function canAdd(
        VoucherCondition $voucher,
        Collection $existing,
        Cart $cart
    ): StackingDecision {
        $policyRules = $this->policy->getRules();

        $sortedRules = collect($policyRules)->sortBy(function (array $ruleConfig): int {
            $type = $ruleConfig['type'] ?? '';
            $rule = $this->getRule($type);

            return $rule?->getPriority() ?? 999;
        });

        foreach ($sortedRules as $ruleConfig) {
            $type = $ruleConfig['type'] ?? '';
            $rule = $this->getRule($type);

            if ($rule === null) {
                continue;
            }

            $decision = $rule->evaluate($voucher, $existing, $cart, $ruleConfig);

            if ($decision->isDenied()) {
                return $decision;
            }
        }

        return StackingDecision::allow();
    }

    /**
     * Calculate the best combination of vouchers.
     *
     * @param  Collection<int, VoucherCondition>  $available
     * @return Collection<int, VoucherCondition>
     */
    public function getBestCombination(
        Collection $available,
        Cart $cart,
        int $maxVouchers = 3
    ): Collection {
        if ($available->count() <= $maxVouchers) {
            return $this->validateCombination($available, $cart);
        }

        if ($this->policy->getMode() === StackingMode::BestDeal) {
            return $this->findBestDealCombination($available, $cart, $maxVouchers);
        }

        return $this->selectByPriority($available, $cart, $maxVouchers);
    }

    /**
     * Calculate total discount for a set of vouchers.
     *
     * @param  Collection<int, VoucherCondition>  $vouchers
     */
    public function calculateCombinationDiscount(Collection $vouchers, Cart $cart): int
    {
        $cartSubtotal = $this->getCartSubtotal($cart);

        if ($this->policy->getMode() === StackingMode::Sequential) {
            return $this->calculateSequentialDiscount($vouchers, $cartSubtotal);
        }

        return $this->calculateParallelDiscount($vouchers, $cartSubtotal);
    }

    /**
     * Register a custom stacking rule.
     */
    public function registerRule(StackingRuleInterface $rule): self
    {
        $this->rules[$rule->getType()] = $rule;

        return $this;
    }

    /**
     * Get a rule by type.
     */
    public function getRule(string $type): ?StackingRuleInterface
    {
        return $this->rules[$type] ?? null;
    }

    /**
     * Get all registered rules.
     *
     * @return array<string, StackingRuleInterface>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    private function registerDefaultRules(): void
    {
        $this->registerRule(new MaxVouchersRule);
        $this->registerRule(new MaxDiscountRule);
        $this->registerRule(new MaxDiscountPercentageRule);
        $this->registerRule(new MutualExclusionRule);
        $this->registerRule(new TypeRestrictionRule);
        $this->registerRule(new CategoryExclusionRule);
        $this->registerRule(new CampaignExclusionRule);
        $this->registerRule(new ValueThresholdRule);
    }

    /**
     * Validate a combination and remove invalid vouchers.
     *
     * @param  Collection<int, VoucherCondition>  $vouchers
     * @return Collection<int, VoucherCondition>
     */
    private function validateCombination(Collection $vouchers, Cart $cart): Collection
    {
        $valid = collect();

        foreach ($vouchers as $voucher) {
            $decision = $this->canAdd($voucher, $valid, $cart);

            if ($decision->isAllowed()) {
                $valid->push($voucher);
            }
        }

        return $valid;
    }

    /**
     * Find the combination that provides the highest discount.
     *
     * @param  Collection<int, VoucherCondition>  $available
     * @return Collection<int, VoucherCondition>
     */
    private function findBestDealCombination(
        Collection $available,
        Cart $cart,
        int $maxVouchers
    ): Collection {
        // Safety guard: if too many vouchers, fall back to priority-based selection
        // to avoid O(2^n) explosion (e.g., 15 vouchers = 32k combinations)
        if ($available->count() > self::MAX_VOUCHERS_FOR_BEST_DEAL) {
            return $this->selectByPriority($available, $cart, $maxVouchers);
        }

        $combinations = $this->generateCombinations($available, $maxVouchers);

        // Safety guard: limit evaluated combinations
        if (count($combinations) > self::MAX_COMBINATIONS_TO_EVALUATE) {
            return $this->selectByPriority($available, $cart, $maxVouchers);
        }

        $best = null;
        $bestDiscount = 0;

        foreach ($combinations as $combo) {
            $validated = $this->validateCombination(collect($combo), $cart);

            if ($validated->isEmpty()) {
                continue;
            }

            $discount = $this->calculateCombinationDiscount($validated, $cart);

            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $best = $validated;
            }
        }

        return $best ?? collect();
    }

    /**
     * Select vouchers by priority until max is reached.
     *
     * @param  Collection<int, VoucherCondition>  $available
     * @return Collection<int, VoucherCondition>
     */
    private function selectByPriority(
        Collection $available,
        Cart $cart,
        int $maxVouchers
    ): Collection {
        $sorted = $available->sortBy(function (VoucherCondition $voucher): int {
            $priority = $voucher->getVoucher()->metadata['stacking_priority'] ?? 100;

            return is_int($priority) ? $priority : 100;
        });

        $selected = collect();

        foreach ($sorted as $voucher) {
            if ($selected->count() >= $maxVouchers) {
                break;
            }

            $decision = $this->canAdd($voucher, $selected, $cart);

            if ($decision->isAllowed()) {
                $selected->push($voucher);
            }
        }

        return $selected;
    }

    /**
     * Generate all combinations of vouchers up to maxSize.
     *
     * @param  Collection<int, VoucherCondition>  $items
     * @return array<int, array<int, VoucherCondition>>
     */
    private function generateCombinations(Collection $items, int $maxSize): array
    {
        $result = [];
        $array = $items->values()->all();
        $n = count($array);

        for ($size = 1; $size <= min($maxSize, $n); $size++) {
            $this->generateCombinationsOfSize($array, $size, 0, [], $result);
        }

        return $result;
    }

    /**
     * @param  array<int, VoucherCondition>  $array
     * @param  array<int, VoucherCondition>  $current
     * @param  array<int, array<int, VoucherCondition>>  $result
     */
    private function generateCombinationsOfSize(
        array $array,
        int $size,
        int $start,
        array $current,
        array &$result
    ): void {
        if (count($current) === $size) {
            $result[] = $current;

            return;
        }

        for ($i = $start; $i < count($array); $i++) {
            $current[] = $array[$i];
            $this->generateCombinationsOfSize($array, $size, $i + 1, $current, $result);
            array_pop($current);
        }
    }

    /**
     * Calculate sequential discount (each applied to remaining total).
     *
     * @param  Collection<int, VoucherCondition>  $vouchers
     */
    private function calculateSequentialDiscount(Collection $vouchers, float $cartSubtotal): int
    {
        $remaining = $cartSubtotal;

        foreach ($vouchers as $voucher) {
            $discountAmount = abs($voucher->getCalculatedValue($remaining));
            $remaining -= $discountAmount;
        }

        return (int) ($cartSubtotal - max(0, $remaining));
    }

    /**
     * Calculate parallel discount (all applied to original total).
     *
     * @param  Collection<int, VoucherCondition>  $vouchers
     */
    private function calculateParallelDiscount(Collection $vouchers, float $cartSubtotal): int
    {
        $totalDiscount = 0;

        foreach ($vouchers as $voucher) {
            $totalDiscount += abs($voucher->getCalculatedValue($cartSubtotal));
        }

        return (int) min($totalDiscount, $cartSubtotal);
    }

    private function getCartSubtotal(Cart $cart): float
    {
        $subtotal = $cart->subtotal();

        return (float) $subtotal->getAmount();
    }
}
