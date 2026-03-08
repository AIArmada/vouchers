<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking\Enums;

/**
 * Defines how multiple vouchers interact when applied to a single cart.
 */
enum StackingMode: string
{
    /**
     * Only one voucher allowed per cart.
     */
    case None = 'none';

    /**
     * Apply vouchers in sequence, each discount applied to remaining total.
     * Example: 20% off RM200 = RM160, then RM30 off = RM130
     */
    case Sequential = 'sequential';

    /**
     * Apply all vouchers to the original total simultaneously.
     * Example: 20% off RM200 = RM40 discount, RM30 off = RM30 discount, total = RM130
     */
    case Parallel = 'parallel';

    /**
     * Automatically select the combination that provides the best value for customer.
     */
    case BestDeal = 'best_deal';

    /**
     * Use custom policy-defined rules for stacking behavior.
     */
    case Custom = 'custom';

    /**
     * Get human-readable label for the mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'Single Voucher Only',
            self::Sequential => 'Sequential Stacking',
            self::Parallel => 'Parallel Stacking',
            self::BestDeal => 'Best Deal Auto-Select',
            self::Custom => 'Custom Policy',
        };
    }

    /**
     * Get description for the mode.
     */
    public function description(): string
    {
        return match ($this) {
            self::None => 'Only one voucher can be applied per cart.',
            self::Sequential => 'Vouchers are applied one after another, each reducing the remaining total.',
            self::Parallel => 'All vouchers are calculated based on the original cart total.',
            self::BestDeal => 'System automatically selects the best voucher combination for the customer.',
            self::Custom => 'Stacking behavior is determined by custom policy rules.',
        };
    }

    /**
     * Check if this mode allows multiple vouchers.
     */
    public function allowsMultipleVouchers(): bool
    {
        return $this !== self::None;
    }
}
