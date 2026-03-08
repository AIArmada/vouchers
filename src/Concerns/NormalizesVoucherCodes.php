<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Concerns;

/**
 * Provides voucher code normalization.
 */
trait NormalizesVoucherCodes
{
    protected function normalizeCode(string $code): string
    {
        $normalized = mb_trim($code);

        if (config('vouchers.code.auto_uppercase', true)) {
            return mb_strtoupper($normalized);
        }

        return $normalized;
    }
}
