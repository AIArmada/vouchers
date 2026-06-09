<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Events;

use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Events\Concerns\HasVoucherEventData;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VoucherUsageRecorded
{
    use Dispatchable;
    use HasVoucherEventData;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly VoucherData $voucher,
        public readonly VoucherUsage $usage,
    ) {
        $this->initializeEventData();
    }

    public function getEventType(): string
    {
        return 'voucher.usage_recorded';
    }
}
