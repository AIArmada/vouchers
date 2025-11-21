<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Traits;

use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasVoucherOwnership
{
    /**
     * Get the vouchers owned by this model.
     */
    public function vouchers(): MorphMany
    {
        return $this->morphMany(Voucher::class, 'owner');
    }
}
