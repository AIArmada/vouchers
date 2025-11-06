<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Contracts;

use Illuminate\Database\Eloquent\Model;

interface VoucherOwnerResolver
{
    public function resolve(): ?Model;
}
