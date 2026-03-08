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
 * Rule that prevents multiple vouchers targeting the same category from stacking.
 */
final class CategoryExclusionRule implements StackingRuleInterface
{
    private const DEFAULT_MAX_PER_CATEGORY = 1;

    private const PRIORITY = 50;

    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $maxPerCategory = $config['max_per_category'] ?? self::DEFAULT_MAX_PER_CATEGORY;
        $newCategories = $this->getTargetCategories($newVoucher);

        if (empty($newCategories)) {
            return StackingDecision::allow();
        }

        foreach ($existingVouchers as $existing) {
            $existingCategories = $this->getTargetCategories($existing);
            $overlap = array_intersect($newCategories, $existingCategories);

            if (! empty($overlap)) {
                $categoryCount = $this->countVouchersForCategories($overlap, $existingVouchers);

                foreach ($overlap as $category) {
                    if (($categoryCount[$category] ?? 0) >= $maxPerCategory) {
                        return StackingDecision::deny(
                            reason: "Maximum of {$maxPerCategory} voucher(s) allowed per category: {$category}",
                            conflictsWith: $existing
                        );
                    }
                }
            }
        }

        return StackingDecision::allow();
    }

    public function getType(): string
    {
        return StackingRuleType::CategoryExclusion->value;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * @return array<string>
     */
    private function getTargetCategories(VoucherCondition $voucher): array
    {
        $voucherData = $voucher->getVoucher();

        $categories = $voucherData->metadata['target_categories'] ?? [];

        return is_array($categories) ? $categories : [];
    }

    /**
     * @param  array<string>  $categories
     * @param  Collection<int, VoucherCondition>  $vouchers
     * @return array<string, int>
     */
    private function countVouchersForCategories(array $categories, Collection $vouchers): array
    {
        $counts = array_fill_keys($categories, 0);

        foreach ($vouchers as $voucher) {
            $voucherCategories = $this->getTargetCategories($voucher);

            foreach ($categories as $category) {
                if (in_array($category, $voucherCategories, true)) {
                    $counts[$category]++;
                }
            }
        }

        return $counts;
    }
}
