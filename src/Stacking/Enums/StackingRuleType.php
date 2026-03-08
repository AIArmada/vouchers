<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking\Enums;

use AIArmada\Vouchers\Stacking\Rules\CampaignExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\CategoryExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\MaxDiscountPercentageRule;
use AIArmada\Vouchers\Stacking\Rules\MaxDiscountRule;
use AIArmada\Vouchers\Stacking\Rules\MaxVouchersRule;
use AIArmada\Vouchers\Stacking\Rules\MutualExclusionRule;
use AIArmada\Vouchers\Stacking\Rules\TypeRestrictionRule;
use AIArmada\Vouchers\Stacking\Rules\ValueThresholdRule;

/**
 * Types of stacking rules that can be applied to voucher combinations.
 */
enum StackingRuleType: string
{
    /**
     * Limit the maximum number of vouchers per cart.
     * Config: ['type' => 'max_vouchers', 'value' => 3]
     */
    case MaxVouchers = 'max_vouchers';

    /**
     * Limit the maximum absolute discount amount.
     * Config: ['type' => 'max_discount', 'value' => 10000] (in cents)
     */
    case MaxDiscount = 'max_discount';

    /**
     * Limit the maximum discount as percentage of cart total.
     * Config: ['type' => 'max_discount_percentage', 'value' => 50]
     */
    case MaxDiscountPercentage = 'max_discount_percentage';

    /**
     * Prevent vouchers from same exclusion groups from stacking.
     * Config: ['type' => 'mutual_exclusion', 'groups' => ['flash_sale', 'clearance']]
     */
    case MutualExclusion = 'mutual_exclusion';

    /**
     * Limit number of vouchers per type (percentage, fixed, free_shipping).
     * Config: ['type' => 'type_restriction', 'max_per_type' => ['percentage' => 1, 'fixed' => 2]]
     */
    case TypeRestriction = 'type_restriction';

    /**
     * Prevent vouchers targeting same categories from stacking.
     * Config: ['type' => 'category_exclusion', 'max_per_category' => 1]
     */
    case CategoryExclusion = 'category_exclusion';

    /**
     * Prevent vouchers from same campaign from stacking.
     * Config: ['type' => 'campaign_exclusion', 'max_per_campaign' => 1]
     */
    case CampaignExclusion = 'campaign_exclusion';

    /**
     * Require minimum cart value for stacking.
     * Config: ['type' => 'value_threshold', 'minimum' => 5000] (in cents)
     */
    case ValueThreshold = 'value_threshold';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::MaxVouchers => 'Maximum Vouchers',
            self::MaxDiscount => 'Maximum Discount Amount',
            self::MaxDiscountPercentage => 'Maximum Discount Percentage',
            self::MutualExclusion => 'Mutual Exclusion Groups',
            self::TypeRestriction => 'Voucher Type Restriction',
            self::CategoryExclusion => 'Category Exclusion',
            self::CampaignExclusion => 'Campaign Exclusion',
            self::ValueThreshold => 'Minimum Cart Value',
        };
    }

    /**
     * Get description for the rule type.
     */
    public function description(): string
    {
        return match ($this) {
            self::MaxVouchers => 'Limits how many vouchers can be applied to a single cart.',
            self::MaxDiscount => 'Caps the total discount amount in absolute value.',
            self::MaxDiscountPercentage => 'Caps the total discount as a percentage of cart value.',
            self::MutualExclusion => 'Prevents vouchers from the same exclusion group from stacking.',
            self::TypeRestriction => 'Limits how many vouchers of each type can be applied.',
            self::CategoryExclusion => 'Prevents multiple vouchers targeting the same category.',
            self::CampaignExclusion => 'Prevents multiple vouchers from the same campaign.',
            self::ValueThreshold => 'Requires minimum cart value for voucher stacking.',
        };
    }

    /**
     * Get the rule class name.
     */
    public function getRuleClass(): string
    {
        return match ($this) {
            self::MaxVouchers => MaxVouchersRule::class,
            self::MaxDiscount => MaxDiscountRule::class,
            self::MaxDiscountPercentage => MaxDiscountPercentageRule::class,
            self::MutualExclusion => MutualExclusionRule::class,
            self::TypeRestriction => TypeRestrictionRule::class,
            self::CategoryExclusion => CategoryExclusionRule::class,
            self::CampaignExclusion => CampaignExclusionRule::class,
            self::ValueThreshold => ValueThresholdRule::class,
        };
    }
}
