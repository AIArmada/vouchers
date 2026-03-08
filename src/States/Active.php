<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\States;

final class Active extends VoucherStatus
{
    public static string $name = 'active';

    public function label(): string
    {
        return 'Active';
    }

    public function description(): string
    {
        return 'Voucher can be used';
    }

    public function canBeUsed(): bool
    {
        return true;
    }

    public function isActive(): bool
    {
        return true;
    }
}
