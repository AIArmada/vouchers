<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Exceptions;

final class ManualRedemptionNotAllowedException extends VoucherException
{
    public static function forVoucher(string $code): self
    {
        return new self("Voucher '{$code}' does not allow manual redemption.");
    }
}
