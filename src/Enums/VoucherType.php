<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Enums;

enum VoucherType: string
{
    // Simple Types
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';

    // Compound Types (require value_config)
    case BuyXGetY = 'buy_x_get_y';
    case Tiered = 'tiered';
    case Bundle = 'bundle';
    case Cashback = 'cashback';

    /**
     * Get all simple voucher types.
     *
     * @return array<self>
     */
    public static function simpleTypes(): array
    {
        return [
            self::Percentage,
            self::Fixed,
            self::FreeShipping,
        ];
    }

    /**
     * Get all compound voucher types.
     *
     * @return array<self>
     */
    public static function compoundTypes(): array
    {
        return [
            self::BuyXGetY,
            self::Tiered,
            self::Bundle,
            self::Cashback,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage Discount',
            self::Fixed => 'Fixed Amount Discount',
            self::FreeShipping => 'Free Shipping',
            self::BuyXGetY => 'Buy X Get Y',
            self::Tiered => 'Tiered Discount',
            self::Bundle => 'Bundle Discount',
            self::Cashback => 'Cashback',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Percentage => 'Reduces cart total by a percentage',
            self::Fixed => 'Reduces cart total by a fixed amount',
            self::FreeShipping => 'Removes shipping costs',
            self::BuyXGetY => 'Buy a quantity of items and get other items free or discounted',
            self::Tiered => 'Discount increases based on cart value thresholds',
            self::Bundle => 'Discount when specific products are purchased together',
            self::Cashback => 'Credits balance to wallet after purchase completion',
        };
    }

    /**
     * Check if this voucher type is a compound type requiring value_config.
     */
    public function isCompound(): bool
    {
        return match ($this) {
            self::BuyXGetY,
            self::Tiered,
            self::Bundle,
            self::Cashback => true,
            default => false,
        };
    }

    /**
     * Check if this voucher type applies at checkout time.
     */
    public function appliesAtCheckout(): bool
    {
        return match ($this) {
            self::Cashback => false,
            default => true,
        };
    }

    /**
     * Check if this voucher type requires post-checkout processing.
     */
    public function requiresPostCheckout(): bool
    {
        return $this === self::Cashback;
    }

    /**
     * Check if this voucher type can apply item-level discounts.
     */
    public function hasItemLevelDiscounts(): bool
    {
        return match ($this) {
            self::BuyXGetY,
            self::Bundle => true,
            default => false,
        };
    }

    /**
     * Get the color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Percentage => 'primary',
            self::Fixed => 'success',
            self::FreeShipping => 'info',
            self::BuyXGetY => 'warning',
            self::Tiered => 'violet',
            self::Bundle => 'rose',
            self::Cashback => 'amber',
        };
    }
}
