<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Facades;

use AIArmada\Vouchers\Services\VoucherService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Vouchers\Data\VoucherData|null find(string $code)
 * @method static \AIArmada\Vouchers\Data\VoucherData findOrFail(string $code)
 * @method static \AIArmada\Vouchers\Data\VoucherData create(array<string, mixed> $data)
 * @method static \AIArmada\Vouchers\Data\VoucherData update(string $code, array<string, mixed> $data)
 * @method static bool delete(string $code)
 * @method static \AIArmada\Vouchers\Data\VoucherValidationResult validate(string $code, mixed $cart)
 * @method static bool isValid(string $code)
 * @method static bool canBeUsedBy(string $code, ?\Illuminate\Database\Eloquent\Model $user = null)
 * @method static int getRemainingUses(string $code)
 * @method static void recordUsage(string $code, \Akaunting\Money\Money $discountAmount, ?string $channel = null, ?array<string, mixed> $metadata = null, ?\Illuminate\Database\Eloquent\Model $redeemedBy = null, ?string $notes = null, ?\AIArmada\Vouchers\Models\Voucher $voucherModel = null)
 * @method static void redeemManually(string $code, \Akaunting\Money\Money $discountAmount, ?string $reference = null, ?array<string, mixed> $metadata = null, ?\Illuminate\Database\Eloquent\Model $redeemedBy = null, ?string $notes = null)
 * @method static \Illuminate\Database\Eloquent\Collection<int, \AIArmada\Vouchers\Models\VoucherUsage> getUsageHistory(string $code)
 * @method static \AIArmada\Vouchers\Models\VoucherWallet addToWallet(string $code, \Illuminate\Database\Eloquent\Model $owner, ?array<string, mixed> $metadata = null)
 * @method static bool removeFromWallet(string $code, \Illuminate\Database\Eloquent\Model $owner)
 *
 * @see VoucherService
 */
class Voucher extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'voucher';
    }
}
