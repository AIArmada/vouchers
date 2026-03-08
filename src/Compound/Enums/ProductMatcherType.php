<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Enums;

/**
 * Types of product matchers available for compound vouchers.
 */
enum ProductMatcherType: string
{
    case Sku = 'sku';
    case Category = 'category';
    case Price = 'price';
    case Attribute = 'attribute';
    case All = 'all';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::Sku => 'SKU Match',
            self::Category => 'Category Match',
            self::Price => 'Price Range',
            self::Attribute => 'Attribute Match',
            self::All => 'Match All (AND)',
            self::Any => 'Match Any (OR)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sku => 'Match products by SKU/product ID',
            self::Category => 'Match products by category',
            self::Price => 'Match products within a price range',
            self::Attribute => 'Match products by custom attribute',
            self::All => 'All conditions must match',
            self::Any => 'Any condition can match',
        };
    }

    /**
     * Check if this is a composite matcher type.
     */
    public function isComposite(): bool
    {
        return match ($this) {
            self::All, self::Any => true,
            default => false,
        };
    }
}
