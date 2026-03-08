<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Exceptions;

class VoucherExpiredException extends VoucherException
{
    public static function withCode(string $code): self
    {
        return new self("Voucher '{$code}' has expired.");
    }
}
