<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\States;

final class Depleted extends VoucherStatus
{
    public static string $name = 'depleted';

    public function label(): string
    {
        return 'Depleted';
    }

    public function description(): string
    {
        return 'Voucher usage limit reached';
    }

    public function isDepleted(): bool
    {
        return true;
    }
}
