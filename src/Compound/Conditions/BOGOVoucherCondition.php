<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Conditions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Enums\ItemSelectionStrategy;
use AIArmada\Vouchers\Contracts\ProductMatcherInterface;
use Illuminate\Support\Collection;

/**
 * Buy X Get Y (BOGO) voucher condition.
 *
 * Supports configurations like:
 * - Buy 2 Get 1 Free
 * - Buy 1 Get 1 50% Off
 * - Buy Any 3, Cheapest Free
 *
 * Configuration example:
 * {
 *   "buy": {
 *     "quantity": 2,
 *     "product_matcher": { "type": "category", "categories": ["shirts"] }
 *   },
 *   "get": {
 *     "quantity": 1,
 *     "discount": "100%",
 *     "selection": "cheapest",
 *     "product_matcher": { "type": "same_as_buy" }
 *   },
 *   "max_applications": 3
 * }
 */
class BOGOVoucherCondition extends CompoundVoucherCondition
{
    public function calculateDiscount(Cart $cart): int
    {
        if (! $this->meetsRequirements($cart)) {
            return 0;
        }

        $applications = $this->calculateApplications($cart);
        $totalDiscount = 0;

        foreach ($applications as $application) {
            $totalDiscount += $application['discount'];
        }

        return $totalDiscount;
    }

    public function getDiscountDescription(Cart $cart): string
    {
        $buyQty = $this->getConfig('buy.quantity', 1);
        $getQty = $this->getConfig('get.quantity', 1);
        $discount = $this->getConfig('get.discount', '100%');

        if ($discount === '100%') {
            return "Buy {$buyQty} Get {$getQty} Free";
        }

        $discountPercent = (int) str_replace('%', '', (string) $discount);

        return "Buy {$buyQty} Get {$getQty} at {$discountPercent}% Off";
    }

    public function meetsRequirements(Cart $cart): bool
    {
        $items = $this->getCartItems($cart);
        $buyMatcher = $this->getBuyMatcher();
        $buyQuantity = $this->getConfig('buy.quantity', 1);

        $matchingItems = $buyMatcher->filter($items);
        $totalQuantity = $matchingItems->sum(fn (CartItem $item): int => $item->quantity);

        return $totalQuantity >= $buyQuantity;
    }

    /**
     * Calculate all applications of this BOGO deal.
     *
     * @return array<int, array{items: array<CartItem>, discount: int}>
     */
    public function calculateApplications(Cart $cart): array
    {
        $items = $this->getCartItems($cart);
        $buyMatcher = $this->getBuyMatcher();
        $getMatcher = $this->getGetMatcher();

        $buyQuantity = (int) $this->getConfig('buy.quantity', 1);
        $getQuantity = (int) $this->getConfig('get.quantity', 1);
        $maxApplications = $this->getConfig('max_applications') ?? PHP_INT_MAX;
        $discount = $this->getConfig('get.discount', '100%');
        $selectionStrategy = $this->getSelectionStrategy();

        // Get all matching items
        $buyItems = $buyMatcher->filter($items);
        $getItems = $getMatcher->filter($items);

        // Sort get items by selection strategy
        $sortedGetItems = $this->sortByStrategy($getItems, $selectionStrategy);

        // Calculate total quantities
        $totalBuyQuantity = $buyItems->sum(fn (CartItem $item): int => $item->quantity);
        $totalGetQuantity = $sortedGetItems->sum(fn (CartItem $item): int => $item->quantity);

        // Calculate how many full applications
        // When buy and get items overlap (same items can satisfy both), use set-based calculation
        $setSize = $buyQuantity + $getQuantity;
        $buyItemIds = $buyItems->pluck('id')->toArray();
        $getItemIds = $sortedGetItems->pluck('id')->toArray();
        $overlapping = ! empty(array_intersect($buyItemIds, $getItemIds));

        if ($overlapping) {
            // Items are shared between buy and get pools - use set-based calculation
            // Each application consumes (buyQuantity + getQuantity) items from the shared pool
            $sharedQuantity = min($totalBuyQuantity, $totalGetQuantity);
            $applicationCount = min(
                (int) floor($sharedQuantity / $setSize),
                $maxApplications
            );
        } else {
            // Items are separate - buy and get pools are independent
            $possibleByBuy = (int) floor($totalBuyQuantity / $buyQuantity);
            $possibleByGet = (int) floor($totalGetQuantity / $getQuantity);
            $applicationCount = min($possibleByBuy, $possibleByGet, $maxApplications);
        }

        if ($applicationCount === 0) {
            return [];
        }

        // Calculate discount per application
        $applications = [];
        $remainingGetQty = $getQuantity * $applicationCount;
        $currentDiscount = 0;

        foreach ($sortedGetItems as $item) {
            if ($remainingGetQty <= 0) {
                break;
            }

            $itemQty = min($item->quantity, $remainingGetQty);
            $itemPrice = $item->getRawPrice();
            $itemDiscount = $this->calculateItemDiscount($itemPrice * $itemQty, $discount);

            $currentDiscount += $itemDiscount;
            $remainingGetQty -= $itemQty;
        }

        // Split discount evenly across applications for tracking
        $discountPerApplication = (int) ceil($currentDiscount / $applicationCount);

        for ($i = 0; $i < $applicationCount; $i++) {
            $applications[] = [
                'items' => $sortedGetItems->take($getQuantity)->all(),
                'discount' => $i === $applicationCount - 1
                    ? $currentDiscount - ($discountPerApplication * ($applicationCount - 1))
                    : $discountPerApplication,
            ];
        }

        return $applications;
    }

    /**
     * Get the buy product matcher.
     */
    protected function getBuyMatcher(): ProductMatcherInterface
    {
        $config = $this->getConfig('buy.product_matcher', ['type' => 'all']);

        return $this->createMatcher($config);
    }

    /**
     * Get the get product matcher.
     */
    protected function getGetMatcher(): ProductMatcherInterface
    {
        $config = $this->getConfig('get.product_matcher', ['type' => 'same_as_buy']);

        // Handle "same_as_buy" reference
        if (($config['type'] ?? '') === 'same_as_buy') {
            return $this->getBuyMatcher();
        }

        return $this->createMatcher($config);
    }

    /**
     * Get the item selection strategy.
     */
    protected function getSelectionStrategy(): ItemSelectionStrategy
    {
        $strategy = $this->getConfig('get.selection', 'cheapest');

        return ItemSelectionStrategy::tryFrom($strategy) ?? ItemSelectionStrategy::Cheapest;
    }

    /**
     * Sort items by selection strategy.
     *
     * @param  Collection<int, CartItem>  $items
     * @return Collection<int, CartItem>
     */
    protected function sortByStrategy(Collection $items, ItemSelectionStrategy $strategy): Collection
    {
        return match ($strategy) {
            ItemSelectionStrategy::Cheapest => $items->sortBy(fn (CartItem $item): int => $item->getRawPrice()),
            ItemSelectionStrategy::MostExpensive => $items->sortByDesc(fn (CartItem $item): int => $item->getRawPrice()),
            ItemSelectionStrategy::First => $items,
            ItemSelectionStrategy::Last => $items->reverse(),
            ItemSelectionStrategy::Random => $items->shuffle(),
        };
    }

    /**
     * Calculate discount for an item based on discount string.
     *
     * @param  int  $price  Price in cents
     * @param  string  $discount  Discount string (e.g., "100%", "50%", "500")
     * @return int Discount amount in cents
     */
    protected function calculateItemDiscount(int $price, string $discount): int
    {
        if (str_ends_with($discount, '%')) {
            $percent = (int) str_replace('%', '', $discount);

            return (int) round($price * ($percent / 100));
        }

        // Fixed discount
        return min((int) $discount, $price);
    }

    protected function getConditionValue(): string
    {
        // Dynamic calculation - will be handled by the cart
        return '-0';
    }
}
