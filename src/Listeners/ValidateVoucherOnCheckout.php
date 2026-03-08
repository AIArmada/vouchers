<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Listeners;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Exceptions\VoucherValidationException;
use AIArmada\Vouchers\Services\VoucherService;

/**
 * Validates vouchers when checkout is initiated.
 *
 * This listener ensures all applied vouchers are still valid
 * at the moment of checkout, preventing stale discounts from
 * being applied to orders.
 */
class ValidateVoucherOnCheckout
{
    private const string VOUCHER_METADATA_KEY = 'voucher_codes';

    public function __construct(
        private readonly VoucherService $voucherService
    ) {}

    /**
     * Handle the checkout started event.
     *
     * Validates all vouchers in the cart and removes any that are no longer valid.
     * Optionally throws an exception if configured to block checkout on invalid vouchers.
     *
     * @param  object  $event  The checkout started event (cart.checkout.started)
     *
     * @throws VoucherValidationException When configured to block on invalid vouchers
     */
    public function handle(object $event): void
    {
        $cart = $this->extractCart($event);

        if ($cart === null) {
            return;
        }

        /** @var array<string> $voucherCodes */
        $voucherCodes = $cart->getMetadata(self::VOUCHER_METADATA_KEY, []);

        if (empty($voucherCodes)) {
            return;
        }

        $invalidCodes = [];
        $validCodes = [];

        foreach ($voucherCodes as $code) {
            $result = $this->voucherService->validate($code, $cart);

            if ($result->isValid) {
                $validCodes[] = $code;
            } else {
                $invalidCodes[$code] = $result->reason ?? 'Voucher is no longer valid';
            }
        }

        // Update cart metadata with only valid vouchers
        if (count($invalidCodes) > 0) {
            $cart->setMetadata(self::VOUCHER_METADATA_KEY, $validCodes);

            // Optionally block checkout on invalid vouchers
            if (config('vouchers.checkout.block_on_invalid', false)) {
                throw VoucherValidationException::multipleInvalid($invalidCodes);
            }
        }
    }

    /**
     * Extract cart from the event.
     */
    private function extractCart(object $event): ?Cart
    {
        if (property_exists($event, 'cart') && $event->cart instanceof Cart) {
            return $event->cart;
        }

        return null;
    }
}
