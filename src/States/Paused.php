<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\States;

final class Paused extends VoucherStatus
{
    public static string $name = 'paused';

    public function label(): string
    {
        return 'Paused';
    }

    public function description(): string
    {
        return 'Voucher temporarily disabled';
    }

    public function isPaused(): bool
    {
        return true;
    }
}
