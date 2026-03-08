<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Enums;

enum VoucherStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Expired = 'expired';
    case Depleted = 'depleted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Expired => 'Expired',
            self::Depleted => 'Depleted',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Active => 'Voucher can be used',
            self::Paused => 'Voucher temporarily disabled',
            self::Expired => 'Voucher past expiry date',
            self::Depleted => 'Voucher usage limit reached',
        };
    }

    public function canBeUsed(): bool
    {
        return $this === self::Active;
    }
}
