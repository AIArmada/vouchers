<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Listeners;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Vouchers\Actions\ValidateVoucherCode;
use AIArmada\Vouchers\Exceptions\VoucherValidationException;
use AIArmada\Vouchers\Support\VoucherCartMetadata;

/**
 * Validates vouchers when checkout is initiated.
 *
 * This listener ensures all applied vouchers are still valid
 * at the moment of checkout, preventing stale discounts from
 * being applied to orders.
 */
class ValidateVoucherOnCheckout
{
    /**
     * Handle the checkout started event.
     *
     * Validates all vouchers in the cart and removes any that are no longer valid.
     * Optionally throws an exception if configured to block checkout on invalid vouchers.
     *
     * @param  object  $event  A cart event or checkout-started event
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
        $voucherCodes = $cart->getMetadata(VoucherCartMetadata::VOUCHER_CODES, []);

        if (empty($voucherCodes)) {
            return;
        }

        $invalidCodes = [];
        $validCodes = [];

        foreach ($voucherCodes as $code) {
            $result = ValidateVoucherCode::run($code, $cart);

            if ($result->isValid) {
                $validCodes[] = $code;
            } else {
                $invalidCodes[$code] = $result->reason ?? 'Voucher is no longer valid';
            }
        }

        // Update cart metadata with only valid vouchers
        if (count($invalidCodes) > 0) {
            $cart->setMetadata(VoucherCartMetadata::VOUCHER_CODES, $validCodes);

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

        if (! property_exists($event, 'session') || ! is_object($event->session)) {
            return null;
        }

        $session = $event->session;
        $cartId = method_exists($session, 'getAttribute')
            ? $session->getAttribute('cart_id')
            : (property_exists($session, 'cart_id') ? $session->cart_id : null);

        if (! is_string($cartId) || mb_trim($cartId) === '' || ! app()->bound(CartManagerInterface::class)) {
            return null;
        }

        return app(CartManagerInterface::class)->getById($cartId);
    }
}
