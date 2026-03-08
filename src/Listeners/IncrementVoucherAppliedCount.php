<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Listeners;

use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;

final class IncrementVoucherAppliedCount
{
    public function handle(VoucherApplied $event): void
    {
        if (! config('vouchers.tracking.track_applications', true)) {
            return;
        }

        $voucherCode = $event->voucher->code;
        $ownerId = $event->voucher->ownerId;
        $ownerType = $event->voucher->ownerType;

        Voucher::withoutGlobalScopes()
            ->where('code', $voucherCode)
            ->when($ownerId !== null && $ownerType !== null, function (Builder $query) use ($ownerId, $ownerType): void {
                $query->where('owner_id', $ownerId)->where('owner_type', $ownerType);
            })
            ->when($ownerId === null || $ownerType === null, function (Builder $query): void {
                $query->whereNull('owner_id');
            })
            ->increment('applied_count');
    }
}
