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
 * Rule that prevents vouchers from the same exclusion group from stacking.
 *
 * Exclusion groups are used to prevent combining incompatible promotions,
 * such as "flash_sale" and "clearance" vouchers.
 */
final class MutualExclusionRule implements StackingRuleInterface
{
    private const PRIORITY = 30;

    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $exclusionGroups = $config['groups'] ?? [];

        if (empty($exclusionGroups)) {
            return StackingDecision::allow();
        }

        $newVoucherGroups = $this->getExclusionGroups($newVoucher);

        if (empty($newVoucherGroups)) {
            return StackingDecision::allow();
        }

        foreach ($existingVouchers as $existing) {
            $existingGroups = $this->getExclusionGroups($existing);

            if (empty($existingGroups)) {
                continue;
            }

            $newRelevantGroups = array_intersect($newVoucherGroups, $exclusionGroups);
            $existingRelevantGroups = array_intersect($existingGroups, $exclusionGroups);
            $conflicts = array_intersect($newRelevantGroups, $existingRelevantGroups);

            if (! empty($conflicts)) {
                $conflictList = implode(', ', $conflicts);

                return StackingDecision::deny(
                    reason: "Cannot combine vouchers from same exclusion group: {$conflictList}",
                    conflictsWith: $existing
                );
            }
        }

        return StackingDecision::allow();
    }

    public function getType(): string
    {
        return StackingRuleType::MutualExclusion->value;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * @return array<string>
     */
    private function getExclusionGroups(VoucherCondition $voucher): array
    {
        $voucherData = $voucher->getVoucher();

        $groups = $voucherData->metadata['exclusion_groups'] ?? [];

        return is_array($groups) ? $groups : [];
    }
}
