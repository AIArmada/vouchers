<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Matchers;

use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Enums\ProductMatcherType;
use AIArmada\Vouchers\Contracts\ProductMatcherInterface;

/**
 * Composite matcher that combines multiple matchers with AND/OR logic.
 */
class CompositeMatcher extends AbstractProductMatcher
{
    private bool $requireAll;

    /** @var array<ProductMatcherInterface> */
    private array $matchers;

    /**
     * @param  array<ProductMatcherInterface>  $matchers
     * @param  bool  $requireAll  If true, ALL matchers must match (AND). If false, ANY matcher can match (OR).
     */
    public function __construct(array $matchers, bool $requireAll = true)
    {
        parent::__construct(['require_all' => $requireAll]);
        $this->matchers = $matchers;
        $this->requireAll = $requireAll;
    }

    /**
     * Create an AND composite matcher.
     *
     * @param  array<ProductMatcherInterface|array<string, mixed>>  $matcherConfigs
     */
    public static function all(array $matcherConfigs): self
    {
        $matchers = array_map(
            fn (ProductMatcherInterface | array $config): ProductMatcherInterface => $config instanceof ProductMatcherInterface
                ? $config
                : AbstractProductMatcher::create($config),
            $matcherConfigs
        );

        return new self($matchers, true);
    }

    /**
     * Create an OR composite matcher.
     *
     * @param  array<ProductMatcherInterface|array<string, mixed>>  $matcherConfigs
     */
    public static function any(array $matcherConfigs): self
    {
        $matchers = array_map(
            fn (ProductMatcherInterface | array $config): ProductMatcherInterface => $config instanceof ProductMatcherInterface
                ? $config
                : AbstractProductMatcher::create($config),
            $matcherConfigs
        );

        return new self($matchers, false);
    }

    public static function fromArray(array $config): self
    {
        $type = $config['type'] ?? 'all';
        $requireAll = $type === ProductMatcherType::All->value;

        $matchers = array_map(
            fn (array $c): ProductMatcherInterface => AbstractProductMatcher::create($c),
            $config['matchers'] ?? []
        );

        return new self($matchers, $requireAll);
    }

    public function matches(CartItem $item): bool
    {
        if (empty($this->matchers)) {
            return true;
        }

        if ($this->requireAll) {
            // AND logic: all matchers must match
            foreach ($this->matchers as $matcher) {
                if (! $matcher->matches($item)) {
                    return false;
                }
            }

            return true;
        }

        // OR logic: any matcher can match
        foreach ($this->matchers as $matcher) {
            if ($matcher->matches($item)) {
                return true;
            }
        }

        return false;
    }

    public function getType(): string
    {
        return $this->requireAll
            ? ProductMatcherType::All->value
            : ProductMatcherType::Any->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'matchers' => array_map(
                fn (ProductMatcherInterface $m): array => $m->toArray(),
                $this->matchers
            ),
        ];
    }

    /**
     * Add a matcher to the composite.
     */
    public function addMatcher(ProductMatcherInterface $matcher): self
    {
        $this->matchers[] = $matcher;

        return $this;
    }

    /**
     * Get all matchers in this composite.
     *
     * @return array<ProductMatcherInterface>
     */
    public function getMatchers(): array
    {
        return $this->matchers;
    }
}
