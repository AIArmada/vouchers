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

        // Intentional owner-scope bypass: the event carries the owner tuple directly from
        // the VoucherApplied event, so we look up by (code + owner) rather than relying
        // on ambient OwnerContext.  withoutGlobalScopes() is safe here because we
        // re-apply the owner constraint manually below.
        Voucher::withoutGlobalScopes()
            ->where('code', $voucherCode)
            ->when($ownerId !== null && $ownerType !== null, function (Builder $query) use ($ownerId, $ownerType): void {
                $query->where('owner_id', $ownerId)->where('owner_type', $ownerType);
            })
            ->when($ownerId === null || $ownerType === null, function (Builder $query): void {
                $query->whereNull('owner_id')->whereNull('owner_type');
            })
            ->increment('applied_count');
    }
}
