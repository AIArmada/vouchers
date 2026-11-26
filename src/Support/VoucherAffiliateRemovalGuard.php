<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Affiliates\Contracts\AffiliateRemovalGuard;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class VoucherAffiliateRemovalGuard implements AffiliateRemovalGuard
{
    public function assertCanRemove(string $affiliateId, int $connectionInstanceId): void
    {
        $voucher = new Voucher;

        if (spl_object_id($voucher->getConnection()) !== $connectionInstanceId) {
            throw new LogicException('Affiliate voucher removal checks require a shared database connection.');
        }

        if (Voucher::query()->withoutOwnerScope()->where('affiliate_id', $affiliateId)->exists()) {
            throw ValidationException::withMessages(['affiliate' => 'An affiliate with linked business or historical records cannot be deleted.']);
        }
    }
}
