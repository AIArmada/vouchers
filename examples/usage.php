<?php

declare(strict_types=1);

/**
 * Voucher System Usage Examples
 *
 * This file demonstrates voucher usage patterns using the Vouchers package
 * itself (models/enums/exceptions).
 *
 * Note: Cart integration lives in the Cart + Vouchers integration layer.
 * This file intentionally avoids referencing optional/cart-only facades so it
 * stays analyzable in standalone installs.
 */

use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Exceptions\VoucherException;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\VoucherStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class VoucherUsageExamples
{
    public static function createPercentageVoucher(): Voucher
    {
        return Voucher::query()->create([
            'code' => 'SUMMER20',
            'type' => VoucherType::Percentage,
            'status' => Active::class,
            'value' => 2000,
            'currency' => 'MYR',
            'description' => '20% off summer sale',
            'starts_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addMonth(),
        ]);
    }

    public static function createFixedVoucher(): Voucher
    {
        return Voucher::query()->create([
            'code' => 'SAVE50',
            'type' => VoucherType::Fixed,
            'status' => Active::class,
            'value' => 5000,
            'currency' => 'MYR',
            'description' => 'RM50 off orders over RM200',
            'min_cart_value' => 20000,
            'starts_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addWeek(),
        ]);
    }

    public static function createFreeShippingVoucher(): Voucher
    {
        return Voucher::query()->create([
            'code' => 'FREESHIP',
            'type' => VoucherType::FreeShipping,
            'status' => Active::class,
            'value' => 0,
            'currency' => 'MYR',
            'description' => 'Free shipping on all orders',
            'starts_at' => CarbonImmutable::now(),
        ]);
    }

    public static function findActiveVoucherByCode(string $code): ?Voucher
    {
        /** @var Builder<Voucher> $query */
        $query = Voucher::query();

        return $query
            ->where('code', $code)
            ->where('status', VoucherStatus::normalize(Active::class))
            ->first();
    }

    public static function exampleErrorHandling(): void
    {
        try {
            throw new VoucherException('Example error.');
        } catch (VoucherException $exception) {
            report($exception);
        }
    }
}
