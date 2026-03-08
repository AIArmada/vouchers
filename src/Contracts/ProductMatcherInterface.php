<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Contracts;

use AIArmada\Cart\Models\CartItem;
use Illuminate\Support\Collection;

/**
 * Interface for matching cart items based on various criteria.
 *
 * Product matchers are used by compound voucher types (BOGO, Bundle) to identify
 * which items in a cart qualify for a promotion.
 */
interface ProductMatcherInterface
{
    /**
     * Create a matcher from configuration array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self;

    /**
     * Check if a cart item matches this matcher's criteria.
     */
    public function matches(CartItem $item): bool;

    /**
     * Filter a collection of cart items to only those matching this matcher.
     *
     * @param  Collection<int, CartItem>  $items
     * @return Collection<int, CartItem>
     */
    public function filter(Collection $items): Collection;

    /**
     * Get all matching items from a collection, with optional limit.
     *
     * @param  Collection<int, CartItem>  $items
     * @return Collection<int, CartItem>
     */
    public function getMatchingItems(Collection $items, ?int $limit = null): Collection;

    /**
     * Get the matcher type identifier.
     */
    public function getType(): string;

    /**
     * Convert matcher configuration to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
