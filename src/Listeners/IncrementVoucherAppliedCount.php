<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Listeners;

use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Models\Voucher;

class IncrementVoucherAppliedCount
{
    /**
     * Handle the event.
     */
    public function handle(VoucherApplied $event): void
    {
        // Only track if enabled
        if (! config('vouchers.tracking.track_applications', true)) {
            return;
        }

        $voucherCode = $event->voucher->code;

        // Increment the applied_count for this voucher
        Voucher::where('code', $voucherCode)
            ->increment('applied_count');
    }
}
