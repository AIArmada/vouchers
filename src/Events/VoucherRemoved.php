<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Events;

use AIArmada\Cart\Cart;
use AIArmada\CommerceSupport\Contracts\Events\VoucherEventInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Events\Concerns\HasVoucherEventData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a voucher is removed from a cart.
 */
class VoucherRemoved implements VoucherEventInterface
{
    use Dispatchable;
    use HasVoucherEventData;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Cart  $cart  The cart that the voucher was removed from
     * @param  VoucherData  $voucher  The voucher that was removed
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly VoucherData $voucher
    ) {
        $this->initializeEventData();
    }

    /**
     * Get the event type identifier.
     */
    public function getEventType(): string
    {
        return 'voucher.removed';
    }
}
