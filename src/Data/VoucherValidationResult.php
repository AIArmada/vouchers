<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Data;

readonly class VoucherValidationResult
{
    public function __construct(
        public bool $isValid,
        public ?string $reason = null,
        /** @var ?array<string, mixed> */
        public ?array $details = null,
    ) {}

    public static function valid(): self
    {
        return new self(isValid: true);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function invalid(string $reason, array $details = []): self
    {
        return new self(
            isValid: false,
            reason: $reason,
            details: $details
        );
    }
}
