<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Exceptions;

class VoucherNotFoundException extends VoucherException
{
    public static function withCode(string $code): self
    {
        return new self("Voucher with code '{$code}' not found.");
    }
}
