<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Traits\InteractsWithVouchers;

/**
 * CartWithVouchers provides voucher functionality by wrapping the base Cart class.
 *
 * This class is provided for convenience but is NOT automatically bound.
 * Applications should manually bind this in their AppServiceProvider if desired.
 *
 * Example:
 * ```php
 * // In AppServiceProvider::register()
 * $this->app->bind(\AIArmada\Cart\Cart::class, \AIArmada\Cart\Vouchers\Support\CartWithVouchers::class);
 * ```
 */
class CartWithVouchers
{
    use InteractsWithVouchers;

    private Cart $cart;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
        $this->ensureVoucherRulesFactory($this->cart);
    }

    /**
     * Delegate all other method calls to the underlying cart
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->cart->{$method}(...$arguments);
    }

    /**
     * Get the underlying cart instance
     */
    public function getCart(): Cart
    {
        return $this->cart;
    }
}
