<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support\Resolvers;

use AIArmada\Vouchers\Contracts\VoucherOwnerResolver;
use Illuminate\Database\Eloquent\Model;

final class NullOwnerResolver implements VoucherOwnerResolver
{
    public function resolve(): ?Model
    {
        return null;
    }
}
