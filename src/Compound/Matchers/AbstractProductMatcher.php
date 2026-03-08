<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Matchers;

use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;
use AIArmada\Vouchers\Contracts\ProductMatcherInterface;
use Illuminate\Support\Collection;

/**
 * Abstract base class for product matchers.
 */
abstract class AbstractProductMatcher implements ProductMatcherInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = []
    ) {}

    /**
     * Create the appropriate matcher based on config type.
     *
     * @param  array<string, mixed>  $config
     */
    final public static function create(array $config): ProductMatcherInterface
    {
        $type = ProductMatcherType::tryFrom($config['type'] ?? 'sku');

        return match ($type) {
            ProductMatcherType::Sku => SkuMatcher::fromArray($config),
            ProductMatcherType::Category => CategoryMatcher::fromArray($config),
            ProductMatcherType::Price => PriceMatcher::fromArray($config),
            ProductMatcherType::Attribute => AttributeMatcher::fromArray($config),
            ProductMatcherType::All => CompositeMatcher::all($config['matchers'] ?? []),
            ProductMatcherType::Any => CompositeMatcher::any($config['matchers'] ?? []),
            default => SkuMatcher::fromArray($config),
        };
    }

    final public function filter(Collection $items): Collection
    {
        return $items->filter(fn (CartItem $item): bool => $this->matches($item));
    }

    final public function getMatchingItems(Collection $items, ?int $limit = null): Collection
    {
        $matching = $this->filter($items);

        if ($limit !== null) {
            return $matching->take($limit);
        }

        return $matching;
    }

    /**
     * Get a config value.
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
