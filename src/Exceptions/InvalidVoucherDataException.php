<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Exceptions;

/**
 * Exception thrown when voucher data contains invalid values.
 *
 * This exception is thrown during VoucherData construction when
 * the provided data violates the expected format (e.g., float values
 * passed where integers are expected for monetary fields).
 */
class InvalidVoucherDataException extends VoucherException
{
    /**
     * Create exception for a float value where integer was expected.
     *
     * @param  string  $field  The field name that contained the invalid value
     * @param  float  $value  The float value that was provided
     * @param  string  $description  Description of expected format
     */
    public static function floatNotAllowed(string $field, float $value, string $description): self
    {
        return new self(
            "VoucherData '{$field}' must be an integer ({$description}). "
            . "Got float: {$value}. For percentages, use basis points (e.g., 1250 for 12.5%)."
        );
    }

    /**
     * Create exception for invalid voucher type.
     */
    public static function invalidType(string $type): self
    {
        return new self("Invalid voucher type: '{$type}'.");
    }

    /**
     * Create exception for invalid voucher status.
     */
    public static function invalidStatus(string $status): self
    {
        return new self("Invalid voucher status: '{$status}'.");
    }

    /**
     * Create exception for missing required field.
     */
    public static function missingField(string $field): self
    {
        return new self("Required field '{$field}' is missing from voucher data.");
    }

    /**
     * Create exception for negative value where positive was expected.
     */
    public static function negativeValue(string $field, int $value): self
    {
        return new self("VoucherData '{$field}' must be a positive integer. Got: {$value}.");
    }
}
