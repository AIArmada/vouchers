<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Provides owner-scoped voucher query helpers.
 */
trait QueriesVouchers
{
    use NormalizesVoucherCodes;

    /**
     * @return Builder<Voucher>
     */
    protected function voucherQuery(): Builder
    {
        return Voucher::query()->forOwner(
            $this->resolveOwner(),
            $this->shouldIncludeGlobal()
        );
    }

    protected function resolveOwner(): ?Model
    {
        if (! config('vouchers.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    protected function shouldIncludeGlobal(): bool
    {
        return (bool) config('vouchers.owner.include_global', false);
    }
}
