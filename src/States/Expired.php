<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\States;

final class Expired extends VoucherStatus
{
    public static string $name = 'expired';

    public function label(): string
    {
        return 'Expired';
    }

    public function description(): string
    {
        return 'Voucher past expiry date';
    }

    public function isExpired(): bool
    {
        return true;
    }
}
