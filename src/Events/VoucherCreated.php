<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Events;

use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Events\Concerns\HasVoucherEventData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VoucherCreated
{
    use Dispatchable;
    use HasVoucherEventData;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly VoucherData $voucher
    ) {
        $this->initializeEventData();
    }

    public function getEventType(): string
    {
        return 'voucher.created';
    }
}
