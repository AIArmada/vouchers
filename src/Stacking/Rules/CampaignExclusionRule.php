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
 * Rule that prevents multiple vouchers from the same campaign from stacking.
 */
final class CampaignExclusionRule implements StackingRuleInterface
{
    private const DEFAULT_MAX_PER_CAMPAIGN = 1;

    private const PRIORITY = 55;

    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision {
        $maxPerCampaign = $config['max_per_campaign'] ?? self::DEFAULT_MAX_PER_CAMPAIGN;
        $newCampaignId = $this->getCampaignId($newVoucher);

        if ($newCampaignId === null) {
            return StackingDecision::allow();
        }

        $campaignCount = $existingVouchers
            ->filter(fn (VoucherCondition $v): bool => $this->getCampaignId($v) === $newCampaignId)
            ->count();

        if ($campaignCount >= $maxPerCampaign) {
            $conflicting = $existingVouchers
                ->first(fn (VoucherCondition $v): bool => $this->getCampaignId($v) === $newCampaignId);

            return StackingDecision::deny(
                reason: "Maximum of {$maxPerCampaign} voucher(s) from the same campaign allowed",
                conflictsWith: $conflicting
            );
        }

        return StackingDecision::allow();
    }

    public function getType(): string
    {
        return StackingRuleType::CampaignExclusion->value;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    private function getCampaignId(VoucherCondition $voucher): ?string
    {
        $voucherData = $voucher->getVoucher();

        return $voucherData->metadata['campaign_id'] ?? null;
    }
}
