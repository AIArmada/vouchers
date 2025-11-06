<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Cart\Cart;
use AIArmada\Cart\CartManager;
use ReflectionClass;

final class CartManagerWithVouchers extends CartManager
{
    private function __construct()
    {
        // Prevent direct instantiation. Use fromCartManager().
    }

    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists(CartWithVouchers::class, $method)) {
            $wrapper = new CartWithVouchers($this->getCurrentCart());

            return $wrapper->{$method}(...$arguments);
        }

        return parent::__call($method, $arguments);
    }

    public static function fromCartManager(CartManager $manager): self
    {
        $reflection = new ReflectionClass($manager);
        $proxyReflection = new ReflectionClass(self::class);

        /** @var self $instance */
        $instance = $proxyReflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $property->setValue($instance, $property->getValue($manager));
        }

        $instance->ensureVoucherRulesFactory($instance->getCurrentCart());

        return $instance;
    }

    public function getCurrentCart(): Cart
    {
        return $this->ensureVoucherRulesFactory(parent::getCurrentCart());
    }

    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        $cart = parent::getCartInstance($name, $identifier);

        return $this->ensureVoucherRulesFactory($cart);
    }

    private function ensureVoucherRulesFactory(Cart $cart): Cart
    {
        $factory = $cart->getRulesFactory();

        if ($factory instanceof VoucherRulesFactory) {
            return $cart;
        }

        if ($factory === null) {
            $cart->withRulesFactory(app(VoucherRulesFactory::class));

            return $cart;
        }

        $cart->withRulesFactory(new VoucherRulesFactory($factory));

        return $cart;
    }
}
