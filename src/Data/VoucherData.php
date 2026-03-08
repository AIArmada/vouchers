<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Data;

use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Exceptions\InvalidVoucherDataException;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\VoucherStatus;
use Akaunting\Money\Money;
use DateTimeImmutable;
use DateTimeInterface;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Voucher data transfer object.
 *
 * Represents a voucher with all its properties and configuration.
 * All monetary values are stored in minor units (cents) as integers.
 * Percentage values are stored in basis points (e.g., 10.50% = 1050).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class VoucherData extends Data
{
    /**
     * @param  int  $value  Value in cents for fixed amounts, or basis points for percentages
     * @param  array<string, mixed>|null  $valueConfig  Configuration for compound voucher types
     * @param  int|null  $minCartValue  Minimum cart value in cents
     * @param  int|null  $maxDiscount  Maximum discount in cents
     * @param  array<string, mixed>|null  $targetDefinition  Target definition for condition application
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly VoucherType $type,
        public readonly int $value,
        public readonly ?array $valueConfig,
        public readonly ?string $creditDestination,
        public readonly int $creditDelayHours,
        public readonly string $currency,
        public readonly ?int $minCartValue,
        public readonly ?int $maxDiscount,
        public readonly ?int $usageLimit,
        public readonly ?int $usageLimitPerUser,
        public readonly bool $allowsManualRedemption,
        public readonly int | string | null $ownerId,
        public readonly ?string $ownerType,
        #[WithCast(DateTimeInterfaceCast::class)]
        public readonly ?DateTimeInterface $startsAt,
        #[WithCast(DateTimeInterfaceCast::class)]
        public readonly ?DateTimeInterface $expiresAt,
        public readonly VoucherStatus $status,
        public readonly ?array $targetDefinition,
        public readonly ?array $metadata,
    ) {}

    /**
     * Create from a Voucher model.
     */
    public static function fromModel(Voucher $voucher): self
    {
        $type = $voucher->type;

        if (! $type instanceof VoucherType) {
            $type = VoucherType::from($type);
        }

        $status = $voucher->status;

        if (! $status instanceof VoucherStatus) {
            $status = VoucherStatus::fromString((string) $status);
        }

        return new self(
            id: $voucher->id,
            code: $voucher->code,
            name: $voucher->name,
            description: $voucher->description,
            type: $type,
            value: (int) $voucher->value,
            valueConfig: $voucher->value_config,
            creditDestination: $voucher->credit_destination,
            creditDelayHours: (int) ($voucher->credit_delay_hours ?? 0),
            currency: $voucher->currency,
            minCartValue: $voucher->min_cart_value !== null ? (int) $voucher->min_cart_value : null,
            maxDiscount: $voucher->max_discount !== null ? (int) $voucher->max_discount : null,
            usageLimit: $voucher->usage_limit,
            usageLimitPerUser: $voucher->usage_limit_per_user,
            allowsManualRedemption: (bool) $voucher->allows_manual_redemption,
            ownerId: $voucher->owner_id,
            ownerType: $voucher->owner_type,
            startsAt: $voucher->starts_at,
            expiresAt: $voucher->expires_at,
            status: $status,
            targetDefinition: $voucher->target_definition,
            metadata: $voucher->metadata,
        );
    }

    /**
     * Create from an array with sensible defaults.
     *
     * This method provides convenient array-based construction with default values.
     *
     * IMPORTANT: All monetary values must be integers:
     * - `value`: cents for fixed amounts, basis points for percentages (e.g., 1000 = 10%)
     * - `min_cart_value`: cents (e.g., 5000 = $50.00)
     * - `max_discount`: cents (e.g., 10000 = $100.00)
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidVoucherDataException If float values are passed where integers are expected
     */
    public static function fromArray(array $data): self
    {
        // Validate integer fields - prevent silent float truncation
        self::validateIntegerField($data, 'value', 'cents for fixed amounts or basis points for percentages');
        self::validateIntegerField($data, 'min_cart_value', 'cents');
        self::validateIntegerField($data, 'minCartValue', 'cents');
        self::validateIntegerField($data, 'max_discount', 'cents');
        self::validateIntegerField($data, 'maxDiscount', 'cents');

        // Normalize enums if passed as strings
        $type = $data['type'] ?? VoucherType::Fixed;
        if (is_string($type)) {
            $type = VoucherType::from($type);
        }

        $status = $data['status'] ?? Active::class;
        $status = VoucherStatus::fromString($status);

        // Get currency with config fallback
        $currency = $data['currency'] ?? (string) config('vouchers.default_currency', 'MYR');

        // Convert date strings to DateTime objects
        $startsAt = $data['starts_at'] ?? $data['startsAt'] ?? null;
        if (is_string($startsAt)) {
            $startsAt = new DateTimeImmutable($startsAt);
        }

        $expiresAt = $data['expires_at'] ?? $data['expiresAt'] ?? null;
        if (is_string($expiresAt)) {
            $expiresAt = new DateTimeImmutable($expiresAt);
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            description: $data['description'] ?? null,
            type: $type,
            value: (int) ($data['value'] ?? 0),
            valueConfig: $data['value_config'] ?? $data['valueConfig'] ?? null,
            creditDestination: $data['credit_destination'] ?? $data['creditDestination'] ?? null,
            creditDelayHours: (int) ($data['credit_delay_hours'] ?? $data['creditDelayHours'] ?? 0),
            currency: $currency,
            minCartValue: isset($data['min_cart_value']) || isset($data['minCartValue'])
                ? (int) ($data['min_cart_value'] ?? $data['minCartValue'])
                : null,
            maxDiscount: isset($data['max_discount']) || isset($data['maxDiscount'])
                ? (int) ($data['max_discount'] ?? $data['maxDiscount'])
                : null,
            usageLimit: $data['usage_limit'] ?? $data['usageLimit'] ?? null,
            usageLimitPerUser: $data['usage_limit_per_user'] ?? $data['usageLimitPerUser'] ?? null,
            allowsManualRedemption: (bool) ($data['allows_manual_redemption'] ?? $data['allowsManualRedemption'] ?? false),
            ownerId: $data['owner_id'] ?? $data['ownerId'] ?? null,
            ownerType: $data['owner_type'] ?? $data['ownerType'] ?? null,
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            status: $status,
            targetDefinition: $data['target_definition'] ?? $data['targetDefinition'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Check if the voucher is currently active.
     */
    public function isActive(): bool
    {
        return $this->status instanceof Active;
    }

    /**
     * Check if the voucher is a percentage discount.
     */
    public function isPercentage(): bool
    {
        return $this->type === VoucherType::Percentage;
    }

    /**
     * Check if the voucher is a fixed amount discount.
     */
    public function isFixed(): bool
    {
        return $this->type === VoucherType::Fixed;
    }

    /**
     * Check if the voucher provides free shipping.
     */
    public function isFreeShipping(): bool
    {
        return $this->type === VoucherType::FreeShipping;
    }

    /**
     * Check if the voucher is a compound type.
     */
    public function isCompound(): bool
    {
        return $this->type->isCompound();
    }

    /**
     * Check if the voucher has expired.
     */
    public function hasExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < now();
    }

    /**
     * Check if the voucher has started.
     */
    public function hasStarted(): bool
    {
        if ($this->startsAt === null) {
            return true;
        }

        return $this->startsAt <= now();
    }

    /**
     * Check if the voucher is within its valid date range.
     */
    public function isWithinDateRange(): bool
    {
        return $this->hasStarted() && ! $this->hasExpired();
    }

    /**
     * Check if the voucher has a minimum cart value requirement.
     */
    public function hasMinCartValue(): bool
    {
        return $this->minCartValue !== null && $this->minCartValue > 0;
    }

    /**
     * Check if a cart value meets the minimum requirement.
     *
     * @param  int  $cartValue  Cart value in minor units (cents)
     */
    public function meetsMinCartValue(int $cartValue): bool
    {
        if (! $this->hasMinCartValue()) {
            return true;
        }

        return $cartValue >= $this->minCartValue;
    }

    /**
     * Get the formatted value for display.
     */
    public function getFormattedValue(): string
    {
        if ($this->isPercentage()) {
            $percentage = $this->value / 100;

            return number_format($percentage, 2) . '%';
        }

        $money = Money::{$this->currency}($this->value);

        return (string) $money;
    }

    /**
     * Validate that a field contains an integer value.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidVoucherDataException If a non-integer float is passed
     */
    private static function validateIntegerField(array $data, string $field, string $description): void
    {
        if (! isset($data[$field])) {
            return;
        }

        $value = $data[$field];

        if (is_float($value) && $value !== floor($value)) {
            throw InvalidVoucherDataException::floatNotAllowed($field, $value, $description);
        }
    }
}
