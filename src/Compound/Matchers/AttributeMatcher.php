<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Matchers;

use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;

/**
 * Matches cart items by custom attribute.
 */
class AttributeMatcher extends AbstractProductMatcher
{
    private string $attribute;

    private string $operator;

    private mixed $value;

    /**
     * @param  string  $attribute  The attribute key to match
     * @param  string  $operator  Comparison operator (=, !=, >, <, >=, <=, in, not_in, contains)
     * @param  mixed  $value  The value to compare against
     */
    public function __construct(string $attribute, string $operator, mixed $value)
    {
        parent::__construct([
            'attribute' => $attribute,
            'operator' => $operator,
            'value' => $value,
        ]);
        $this->attribute = $attribute;
        $this->operator = $operator;
        $this->value = $value;
    }

    public static function fromArray(array $config): self
    {
        return new self(
            attribute: $config['attribute'] ?? '',
            operator: $config['operator'] ?? '=',
            value: $config['value'] ?? null
        );
    }

    public function matches(CartItem $item): bool
    {
        $attributes = $item->attributes->toArray();
        $itemValue = $this->getNestedAttribute($attributes, $this->attribute);

        return $this->compare($itemValue, $this->operator, $this->value);
    }

    public function getType(): string
    {
        return ProductMatcherType::Attribute->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'attribute' => $this->attribute,
            'operator' => $this->operator,
            'value' => $this->value,
        ];
    }

    /**
     * Get a nested attribute using dot notation.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function getNestedAttribute(array $attributes, string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $attributes;

        foreach ($keys as $k) {
            if (! is_array($value) || ! array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Compare values using the specified operator.
     */
    private function compare(mixed $itemValue, string $operator, mixed $targetValue): bool
    {
        return match ($operator) {
            '=', '==' => $itemValue === $targetValue,
            '===' => $itemValue === $targetValue,
            '!=' => $itemValue !== $targetValue,
            '!==' => $itemValue !== $targetValue,
            '>' => is_numeric($itemValue) && is_numeric($targetValue) && $itemValue > $targetValue,
            '<' => is_numeric($itemValue) && is_numeric($targetValue) && $itemValue < $targetValue,
            '>=' => is_numeric($itemValue) && is_numeric($targetValue) && $itemValue >= $targetValue,
            '<=' => is_numeric($itemValue) && is_numeric($targetValue) && $itemValue <= $targetValue,
            'in' => is_array($targetValue) && in_array($itemValue, $targetValue, true),
            'not_in' => is_array($targetValue) && ! in_array($itemValue, $targetValue, true),
            'contains' => is_string($itemValue) && is_string($targetValue) && str_contains($itemValue, $targetValue),
            'starts_with' => is_string($itemValue) && is_string($targetValue) && str_starts_with($itemValue, $targetValue),
            'ends_with' => is_string($itemValue) && is_string($targetValue) && str_ends_with($itemValue, $targetValue),
            'exists' => $itemValue !== null,
            'not_exists' => $itemValue === null,
            default => false,
        };
    }
}
