<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Vouchers\Data\VoucherData|null find(string $code)
 * @method static \AIArmada\Vouchers\Data\VoucherData create(array<string, mixed> $data)
 * @method static \AIArmada\Vouchers\Data\VoucherData update(string $code, array<string, mixed> $data)
 * @method static bool delete(string $code)
 * @method static \AIArmada\Vouchers\Data\VoucherValidationResult validate(string $code, mixed $cart)
 * @method static bool isValid(string $code)
 * @method static bool canBeUsedBy(string $code, string $userIdentifier)
 * @method static int getRemainingUses(string $code)
 * @method static void recordUsage(string $code, \Akaunting\Money\Money $discountAmount, ?string $channel = null, ?array<string, mixed> $metadata = null, ?\Illuminate\Database\Eloquent\Model $redeemedBy = null, ?string $notes = null)
 * @method static void redeemManually(string $code, string $userIdentifier, \Akaunting\Money\Money $discountAmount, ?string $reference = null, ?array<string, mixed> $metadata = null, ?\Illuminate\Database\Eloquent\Model $redeemedBy = null, ?string $notes = null)
 * @method static \Illuminate\Support\Collection<int, \AIArmada\Vouchers\Models\VoucherUsage> getUsageHistory(string $code)
 *
 * @see \AIArmada\Vouchers\Services\VoucherService
 */
class Voucher extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'voucher';
    }
}
