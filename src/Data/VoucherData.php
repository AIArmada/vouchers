<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Data;

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Carbon\CarbonImmutable;
use DateTimeInterface;

readonly class VoucherData
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public ?string $description,
        public VoucherType $type,
        public float $value,
        public string $currency,
        public ?float $minCartValue,
        public ?float $maxDiscount,
        public ?int $usageLimit,
        public ?int $usageLimitPerUser,
        public int $timesUsed,
        public bool $allowsManualRedemption,
        public int|string|null $ownerId,
        public ?string $ownerType,
        public ?DateTimeInterface $startsAt,
        public ?DateTimeInterface $expiresAt,
        public VoucherStatus $status,
        /** @var ?array<int|string, mixed> */
        public ?array $applicableProducts,
        /** @var ?array<int|string, mixed> */
        public ?array $excludedProducts,
        /** @var ?array<int|string, mixed> */
        public ?array $applicableCategories,
        /** @var ?array<string, mixed> */
        public ?array $metadata,
    ) {}

    public static function fromModel(mixed $voucher): self
    {
        $type = $voucher->type;

        if (! $type instanceof VoucherType) {
            $type = VoucherType::from($type);
        }

        $status = $voucher->status;

        if (! $status instanceof VoucherStatus) {
            $status = VoucherStatus::from($status);
        }

        return new self(
            id: $voucher->id,
            code: $voucher->code,
            name: $voucher->name,
            description: $voucher->description,
            type: $type,
            value: (float) $voucher->value,
            currency: $voucher->currency,
            minCartValue: $voucher->min_cart_value ? (float) $voucher->min_cart_value : null,
            maxDiscount: $voucher->max_discount ? (float) $voucher->max_discount : null,
            usageLimit: $voucher->usage_limit,
            usageLimitPerUser: $voucher->usage_limit_per_user,
            timesUsed: $voucher->times_used !== null ? (int) $voucher->times_used : 0,
            allowsManualRedemption: (bool) $voucher->allows_manual_redemption,
            ownerId: $voucher->owner_id,
            ownerType: $voucher->owner_type,
            startsAt: $voucher->starts_at,
            expiresAt: $voucher->expires_at,
            status: $status,
            applicableProducts: $voucher->applicable_products,
            excludedProducts: $voucher->excluded_products,
            applicableCategories: $voucher->applicable_categories,
            metadata: $voucher->metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $startsAt = isset($data['starts_at'])
            ? CarbonImmutable::parse((string) $data['starts_at'])
            : null;

        $expiresAt = isset($data['expires_at'])
            ? CarbonImmutable::parse((string) $data['expires_at'])
            : null;

        return new self(
            id: isset($data['id']) ? (string) $data['id'] : '',
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            type: VoucherType::from($data['type'] ?? VoucherType::Fixed->value),
            value: isset($data['value']) ? (float) $data['value'] : 0.0,
            currency: (string) ($data['currency'] ?? 'MYR'),
            minCartValue: isset($data['min_cart_value']) ? (float) $data['min_cart_value'] : null,
            maxDiscount: isset($data['max_discount']) ? (float) $data['max_discount'] : null,
            usageLimit: isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            usageLimitPerUser: isset($data['usage_limit_per_user']) ? (int) $data['usage_limit_per_user'] : null,
            timesUsed: isset($data['times_used']) ? (int) $data['times_used'] : 0,
            allowsManualRedemption: (bool) ($data['allows_manual_redemption'] ?? false),
            ownerId: $data['owner_id'] ?? null,
            ownerType: isset($data['owner_type']) ? (string) $data['owner_type'] : null,
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            status: VoucherStatus::from($data['status'] ?? VoucherStatus::Active->value),
            applicableProducts: isset($data['applicable_products']) && is_array($data['applicable_products'])
                ? $data['applicable_products']
                : null,
            excludedProducts: isset($data['excluded_products']) && is_array($data['excluded_products'])
                ? $data['excluded_products']
                : null,
            applicableCategories: isset($data['applicable_categories']) && is_array($data['applicable_categories'])
                ? $data['applicable_categories']
                : null,
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'value' => $this->value,
            'currency' => $this->currency,
            'min_cart_value' => $this->minCartValue,
            'max_discount' => $this->maxDiscount,
            'usage_limit' => $this->usageLimit,
            'usage_limit_per_user' => $this->usageLimitPerUser,
            'times_used' => $this->timesUsed,
            'allows_manual_redemption' => $this->allowsManualRedemption,
            'owner_id' => $this->ownerId,
            'owner_type' => $this->ownerType,
            'starts_at' => $this->startsAt?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'status' => $this->status->value,
            'applicable_products' => $this->applicableProducts,
            'excluded_products' => $this->excludedProducts,
            'applicable_categories' => $this->applicableCategories,
            'metadata' => $this->metadata,
        ];
    }
}
