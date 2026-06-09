<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking;

use AIArmada\Vouchers\Stacking\Contracts\StackingRuleInterface;
use InvalidArgumentException;

final class StackingRuleRegistry
{
    /** @var array<string, StackingRuleInterface> */
    private array $rules = [];

    public function register(StackingRuleInterface $rule): void
    {
        $this->rules[$rule->getType()] = $rule;
    }

    public function get(string $type): StackingRuleInterface
    {
        if (! isset($this->rules[$type])) {
            throw new InvalidArgumentException("Stacking rule [{$type}] is not registered.");
        }

        return $this->rules[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->rules[$type]);
    }

    /**
     * @return array<string, StackingRuleInterface>
     */
    public function all(): array
    {
        return $this->rules;
    }
}
