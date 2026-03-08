<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Enums;

/**
 * Strategy for selecting items in BOGO and similar promotions.
 */
enum ItemSelectionStrategy: string
{
    case Cheapest = 'cheapest';
    case MostExpensive = 'most_expensive';
    case First = 'first';
    case Last = 'last';
    case Random = 'random';

    public function label(): string
    {
        return match ($this) {
            self::Cheapest => 'Cheapest Item',
            self::MostExpensive => 'Most Expensive Item',
            self::First => 'First Added',
            self::Last => 'Last Added',
            self::Random => 'Random',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Cheapest => 'Select the cheapest matching items for the discount',
            self::MostExpensive => 'Select the most expensive matching items for the discount',
            self::First => 'Select items in the order they were added to cart',
            self::Last => 'Select the most recently added items first',
            self::Random => 'Select items randomly',
        };
    }
}
