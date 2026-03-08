<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Exceptions;

/**
 * Exception thrown when voucher validation fails during checkout.
 */
class VoucherValidationException extends VoucherException
{
    /**
     * @var array<string, string>
     */
    private array $invalidVouchers = [];

    /**
     * Create exception for a single invalid voucher.
     */
    public static function invalid(string $code, string $reason): self
    {
        $exception = new self("Voucher '{$code}' is no longer valid: {$reason}");
        $exception->invalidVouchers = [$code => $reason];

        return $exception;
    }

    /**
     * Create exception for multiple invalid vouchers.
     *
     * @param  array<string, string>  $invalidVouchers  Map of code => reason
     */
    public static function multipleInvalid(array $invalidVouchers): self
    {
        $count = count($invalidVouchers);
        $codes = implode(', ', array_keys($invalidVouchers));

        $exception = new self("{$count} voucher(s) are no longer valid: {$codes}");
        $exception->invalidVouchers = $invalidVouchers;

        return $exception;
    }

    /**
     * Get the list of invalid vouchers.
     *
     * @return array<string, string>
     */
    public function getInvalidVouchers(): array
    {
        return $this->invalidVouchers;
    }
}
