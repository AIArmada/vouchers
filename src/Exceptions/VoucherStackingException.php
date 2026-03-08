<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Exceptions;

use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Stacking\StackingDecision;

/**
 * Exception thrown when voucher stacking rules are violated.
 */
class VoucherStackingException extends VoucherException
{
    private ?VoucherCondition $conflictsWith = null;

    private ?VoucherCondition $suggestedReplacement = null;

    /**
     * Create from a stacking decision.
     */
    public static function fromDecision(StackingDecision $decision): self
    {
        $exception = new self($decision->getReason());
        $exception->conflictsWith = $decision->conflictsWith;
        $exception->suggestedReplacement = $decision->suggestedReplacement;

        return $exception;
    }

    /**
     * Create when maximum vouchers is exceeded.
     */
    public static function maxVouchersExceeded(int $max): self
    {
        return new self("Maximum of {$max} voucher(s) allowed per cart.");
    }

    /**
     * Create when stacking is not allowed.
     */
    public static function stackingNotAllowed(): self
    {
        return new self('Voucher stacking is not allowed.');
    }

    /**
     * Create when vouchers are mutually exclusive.
     */
    public static function mutuallyExclusive(string $newCode, string $existingCode): self
    {
        return new self(
            "Voucher '{$newCode}' cannot be combined with '{$existingCode}'."
        );
    }

    /**
     * Get the conflicting voucher if available.
     */
    public function getConflictingVoucher(): ?VoucherCondition
    {
        return $this->conflictsWith;
    }

    /**
     * Get the suggested replacement voucher if available.
     */
    public function getSuggestedReplacement(): ?VoucherCondition
    {
        return $this->suggestedReplacement;
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
}
