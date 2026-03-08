<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Matchers;

use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;

/**
 * Matches cart items by SKU/product ID.
 */
class SkuMatcher extends AbstractProductMatcher
{
    /** @var array<string> */
    private array $skus;

    private bool $exclude;

    /**
     * @param  array<string>  $skus
     */
    public function __construct(array $skus, bool $exclude = false)
    {
        parent::__construct(['skus' => $skus, 'exclude' => $exclude]);
        $this->skus = array_map('strtoupper', $skus);
        $this->exclude = $exclude;
    }

    public static function fromArray(array $config): self
    {
        return new self(
            skus: $config['skus'] ?? [],
            exclude: $config['exclude'] ?? false
        );
    }

    public function matches(CartItem $item): bool
    {
        $itemSku = mb_strtoupper($this->getItemSku($item));
        $inList = in_array($itemSku, $this->skus, true);

        return $this->exclude ? ! $inList : $inList;
    }

    public function getType(): string
    {
        return ProductMatcherType::Sku->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'skus' => $this->skus,
            'exclude' => $this->exclude,
        ];
    }

    /**
     * Get the SKU from a cart item.
     */
    private function getItemSku(CartItem $item): string
    {
        // Try multiple possible SKU sources
        $attributes = $item->attributes->toArray();

        return (string) (
            $attributes['sku']
            ?? $attributes['product_sku']
            ?? $attributes['model_id']
            ?? $item->id
            ?? ''
        );
    }
}
