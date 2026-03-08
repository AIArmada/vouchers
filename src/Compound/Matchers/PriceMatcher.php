<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Matchers;

use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;

/**
 * Matches cart items by price range.
 */
class PriceMatcher extends AbstractProductMatcher
{
    private ?int $minPrice;

    private ?int $maxPrice;

    private bool $useUnitPrice;

    /**
     * @param  int|null  $minPrice  Minimum price in cents (inclusive)
     * @param  int|null  $maxPrice  Maximum price in cents (inclusive)
     * @param  bool  $useUnitPrice  Use unit price instead of line total
     */
    public function __construct(?int $minPrice = null, ?int $maxPrice = null, bool $useUnitPrice = true)
    {
        parent::__construct([
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'use_unit_price' => $useUnitPrice,
        ]);
        $this->minPrice = $minPrice;
        $this->maxPrice = $maxPrice;
        $this->useUnitPrice = $useUnitPrice;
    }

    public static function fromArray(array $config): self
    {
        return new self(
            minPrice: $config['min_price'] ?? null,
            maxPrice: $config['max_price'] ?? null,
            useUnitPrice: $config['use_unit_price'] ?? true
        );
    }

    public function matches(CartItem $item): bool
    {
        $price = $this->getItemPrice($item);

        if ($this->minPrice !== null && $price < $this->minPrice) {
            return false;
        }

        if ($this->maxPrice !== null && $price > $this->maxPrice) {
            return false;
        }

        return true;
    }

    public function getType(): string
    {
        return ProductMatcherType::Price->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'use_unit_price' => $this->useUnitPrice,
        ];
    }

    /**
     * Get the price from a cart item.
     */
    private function getItemPrice(CartItem $item): int
    {
        if ($this->useUnitPrice) {
            return $item->getRawPrice();
        }

        return $item->getRawSubtotal();
    }
}
