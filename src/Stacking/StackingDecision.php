<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking;

use AIArmada\Vouchers\Conditions\VoucherCondition;

/**
 * Immutable value object representing the decision of whether a voucher can be stacked.
 *
 * @property-read bool $allowed Whether the stacking is allowed
 * @property-read string|null $reason The reason for denial (null if allowed)
 * @property-read VoucherCondition|null $conflictsWith The voucher that conflicts (null if allowed)
 * @property-read VoucherCondition|null $suggestedReplacement Alternative voucher to use
 */
final readonly class StackingDecision
{
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?VoucherCondition $conflictsWith = null,
        public ?VoucherCondition $suggestedReplacement = null,
    ) {}

    /**
     * Create an allowed decision.
     */
    public static function allow(): self
    {
        return new self(allowed: true);
    }

    /**
     * Create a denied decision.
     */
    public static function deny(
        string $reason,
        ?VoucherCondition $conflictsWith = null,
        ?VoucherCondition $suggestedReplacement = null,
    ): self {
        return new self(
            allowed: false,
            reason: $reason,
            conflictsWith: $conflictsWith,
            suggestedReplacement: $suggestedReplacement,
        );
    }

    /**
     * Check if the decision allows stacking.
     */
    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Check if the decision denies stacking.
     */
    public function isDenied(): bool
    {
        return ! $this->allowed;
    }

    /**
     * Check if there's a conflict with another voucher.
     */
    public function hasConflict(): bool
    {
        return $this->conflictsWith !== null;
    }

    /**
     * Check if there's a suggested replacement.
     */
    public function hasSuggestedReplacement(): bool
    {
        return $this->suggestedReplacement !== null;
    }

    /**
     * Get the denial reason or a default message.
     */
    public function getReason(): string
    {
        return $this->reason ?? 'Voucher stacking not allowed';
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{allowed: bool, reason: string|null, conflicts_with: string|null, suggested_replacement: string|null}
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'conflicts_with' => $this->conflictsWith?->getVoucherCode(),
            'suggested_replacement' => $this->suggestedReplacement?->getVoucherCode(),
        ];
    }
}
