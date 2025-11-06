<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Enums;

enum VoucherType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage Discount',
            self::Fixed => 'Fixed Amount Discount',
            self::FreeShipping => 'Free Shipping',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Percentage => 'Reduces cart total by a percentage',
            self::Fixed => 'Reduces cart total by a fixed amount',
            self::FreeShipping => 'Removes shipping costs',
        };
    }
}
