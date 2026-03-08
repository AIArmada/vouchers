<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Conditions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;

/**
 * Bundle discount voucher condition.
 *
 * Applies discount when specific products are purchased together.
 *
 * Configuration example:
 * {
 *   "required_products": [
 *     { "sku": "LAPTOP-001", "quantity": 1 },
 *     { "sku": "MOUSE-001", "quantity": 1 },
 *     { "sku": "KEYBOARD-001", "quantity": 1 }
 *   ],
 *   "discount": "-20%",
 *   "discount_applies_to": "bundle",
 *   "allow_multiples": false,
 *   "bundle_name": "Work From Home Kit"
 * }
 */
class BundleVoucherCondition extends CompoundVoucherCondition
{
    public function calculateDiscount(Cart $cart): int
    {
        $bundleCount = $this->countCompleteBundles($cart);

        if ($bundleCount === 0) {
            return 0;
        }

        $discount = $this->getConfig('discount', '0');
        $discountAppliesTo = $this->getConfig('discount_applies_to', 'bundle');

        return match ($discountAppliesTo) {
            'bundle' => $this->calculateBundleDiscount($cart, $bundleCount, $discount),
            'cart' => $this->calculateCartDiscount($cart, $discount),
            default => $this->calculateBundleDiscount($cart, $bundleCount, $discount),
        };
    }

    public function getDiscountDescription(Cart $cart): string
    {
        $bundleName = $this->getConfig('bundle_name', 'Bundle');
        $discount = $this->getConfig('discount', '0');
        $bundleCount = $this->countCompleteBundles($cart);

        if ($bundleCount === 0) {
            $missing = $this->getMissingProducts($cart);
            if (! empty($missing)) {
                $missingCount = count($missing);

                return "{$bundleName}: Add {$missingCount} more item(s) to complete";
            }

            return $bundleName;
        }

        if (str_ends_with($discount, '%')) {
            $percent = abs((int) str_replace(['%', '-', '+'], '', $discount));

            return "{$bundleName}: {$percent}% off";
        }

        $amount = abs((int) str_replace(['-', '+'], '', $discount)) / 100;

        return "{$bundleName}: RM{$amount} off";
    }

    public function meetsRequirements(Cart $cart): bool
    {
        return $this->countCompleteBundles($cart) >= 1;
    }

    /**
     * Count how many complete bundles are in the cart.
     */
    public function countCompleteBundles(Cart $cart): int
    {
        $required = $this->getRequiredProducts();
        $items = $this->getCartItems($cart);
        $allowMultiples = (bool) $this->getConfig('allow_multiples', false);

        if (empty($required)) {
            return 0;
        }

        // Count available quantity for each required product
        $availableCounts = [];
        foreach ($required as $req) {
            $sku = mb_strtoupper($req['sku'] ?? '');
            $requiredQty = $req['quantity'] ?? 1;

            $matchingItem = $items->first(function (CartItem $item) use ($sku): bool {
                $itemSku = mb_strtoupper($this->getItemSku($item));

                return $itemSku === $sku;
            });

            if ($matchingItem === null) {
                return 0; // Missing required product
            }

            $availableQty = $matchingItem->quantity;
            $bundlesPossible = (int) floor($availableQty / $requiredQty);

            $availableCounts[] = $bundlesPossible;
        }

        $completeBundles = min($availableCounts);

        if (! $allowMultiples) {
            return min($completeBundles, 1);
        }

        return $completeBundles;
    }

    /**
     * Get the required products for the bundle.
     *
     * @return array<int, array{sku: string, quantity: int}>
     */
    public function getRequiredProducts(): array
    {
        $products = $this->getConfig('required_products', []);

        if (! is_array($products)) {
            return [];
        }

        return $products;
    }

    /**
     * Get products missing from the cart to complete a bundle.
     *
     * @return array<int, array{sku: string, quantity_needed: int}>
     */
    public function getMissingProducts(Cart $cart): array
    {
        $required = $this->getRequiredProducts();
        $items = $this->getCartItems($cart);
        $missing = [];

        foreach ($required as $req) {
            $sku = mb_strtoupper($req['sku'] ?? '');
            $requiredQty = $req['quantity'] ?? 1;

            $matchingItem = $items->first(function (CartItem $item) use ($sku): bool {
                $itemSku = mb_strtoupper($this->getItemSku($item));

                return $itemSku === $sku;
            });

            $availableQty = $matchingItem !== null ? $matchingItem->quantity : 0;

            if ($availableQty < $requiredQty) {
                $missing[] = [
                    'sku' => $req['sku'] ?? '',
                    'quantity_needed' => $requiredQty - $availableQty,
                ];
            }
        }

        return $missing;
    }

    /**
     * Calculate the total value of bundle items.
     */
    public function getBundleValue(Cart $cart): int
    {
        $required = $this->getRequiredProducts();
        $items = $this->getCartItems($cart);
        $totalValue = 0;

        foreach ($required as $req) {
            $sku = mb_strtoupper($req['sku'] ?? '');
            $requiredQty = $req['quantity'] ?? 1;

            $matchingItem = $items->first(function (CartItem $item) use ($sku): bool {
                $itemSku = mb_strtoupper($this->getItemSku($item));

                return $itemSku === $sku;
            });

            if ($matchingItem !== null) {
                $totalValue += $matchingItem->getRawPrice() * $requiredQty;
            }
        }

        return $totalValue;
    }

    /**
     * Calculate bundle-specific discount.
     */
    protected function calculateBundleDiscount(Cart $cart, int $bundleCount, string $discount): int
    {
        $bundleValue = $this->getBundleValue($cart) * $bundleCount;

        if (str_ends_with($discount, '%')) {
            $percent = abs((float) str_replace(['%', '-', '+'], '', $discount));

            return (int) round($bundleValue * ($percent / 100));
        }

        // Fixed discount per bundle
        $amount = abs((int) str_replace(['-', '+'], '', $discount));

        return min($amount * $bundleCount, $bundleValue);
    }

    /**
     * Calculate cart-wide discount (when bundle unlocks cart discount).
     */
    protected function calculateCartDiscount(Cart $cart, string $discount): int
    {
        $cartValue = $cart->getRawSubtotal();

        if (str_ends_with($discount, '%')) {
            $percent = abs((float) str_replace(['%', '-', '+'], '', $discount));

            return (int) round($cartValue * ($percent / 100));
        }

        $amount = abs((int) str_replace(['-', '+'], '', $discount));

        return min($amount, $cartValue);
    }

    /**
     * Get the SKU from a cart item.
     */
    protected function getItemSku(CartItem $item): string
    {
        $attributes = $item->attributes->toArray();

        // Try to get from attributes
        $sku = $attributes['sku']
            ?? $attributes['product_sku']
            ?? $attributes['model_id']
            ?? null;

        if ($sku !== null) {
            return (string) $sku;
        }

        // Try to get from associated model if it has an id
        $model = $item->getAssociatedModel();
        if (is_object($model) && property_exists($model, 'id')) {
            return (string) $model->id;
        }

        // Fallback to cart item id
        return (string) $item->id;
    }

    protected function getConditionValue(): string
    {
        return '-0';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConditionAttributes(): array
    {
        return array_merge(parent::getConditionAttributes(), [
            'bundle_name' => $this->getConfig('bundle_name', 'Bundle'),
            'required_products' => $this->getRequiredProducts(),
        ]);
    }
}
