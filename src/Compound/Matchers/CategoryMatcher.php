<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Matchers;

use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;

/**
 * Matches cart items by category.
 */
class CategoryMatcher extends AbstractProductMatcher
{
    /** @var array<string> */
    private array $categories;

    private bool $exclude;

    private bool $includeChildren;

    /**
     * @param  array<string>  $categories
     */
    public function __construct(array $categories, bool $exclude = false, bool $includeChildren = true)
    {
        parent::__construct([
            'categories' => $categories,
            'exclude' => $exclude,
            'include_children' => $includeChildren,
        ]);
        $this->categories = array_map('strtolower', $categories);
        $this->exclude = $exclude;
        $this->includeChildren = $includeChildren;
    }

    public static function fromArray(array $config): self
    {
        return new self(
            categories: $config['categories'] ?? [],
            exclude: $config['exclude'] ?? false,
            includeChildren: $config['include_children'] ?? true
        );
    }

    public function matches(CartItem $item): bool
    {
        $itemCategories = $this->getItemCategories($item);
        $inCategory = false;

        foreach ($itemCategories as $category) {
            $normalizedCategory = mb_strtolower($category);

            if (in_array($normalizedCategory, $this->categories, true)) {
                $inCategory = true;

                break;
            }

            // Check if category is a child of any target category
            if ($this->includeChildren) {
                foreach ($this->categories as $targetCategory) {
                    if (str_starts_with($normalizedCategory, $targetCategory . '/')) {
                        $inCategory = true;

                        break 2;
                    }
                }
            }
        }

        return $this->exclude ? ! $inCategory : $inCategory;
    }

    public function getType(): string
    {
        return ProductMatcherType::Category->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'categories' => $this->categories,
            'exclude' => $this->exclude,
            'include_children' => $this->includeChildren,
        ];
    }

    /**
     * Get categories from a cart item.
     *
     * @return array<string>
     */
    private function getItemCategories(CartItem $item): array
    {
        $attributes = $item->attributes->toArray();

        // Try multiple possible category sources
        $categories = $attributes['categories'] ?? $attributes['category'] ?? [];

        if (is_string($categories)) {
            return [$categories];
        }

        return is_array($categories) ? $categories : [];
    }
}
