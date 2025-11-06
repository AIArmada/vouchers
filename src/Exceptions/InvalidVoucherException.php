<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Exceptions;

class InvalidVoucherException extends VoucherException
{
    public static function notActive(string $code): self
    {
        return new self("Voucher '{$code}' is not active.");
    }

    public static function notStarted(string $code): self
    {
        return new self("Voucher '{$code}' is not yet available.");
    }

    public static function minCartValue(string $code, string $minValue): self
    {
        return new self("Voucher '{$code}' requires a minimum cart value of {$minValue}.");
    }
}
