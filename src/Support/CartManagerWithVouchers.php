<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Cart\Cart;
use AIArmada\Cart\CartManager;
use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use Throwable;

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

        $currentCartProperty = self::resolveProperty($reflection, 'currentCart');

        if ($currentCartProperty === null) {
            throw new RuntimeException('Unable to locate CartManager::$currentCart property.');
        }

        self::ensurePropertyInitialized($manager, $currentCartProperty);

        foreach (self::walkClassHierarchy($reflection) as $class) {
            foreach ($class->getProperties() as $property) {
                if ($property->isStatic() || ! $property->isInitialized($manager)) {
                    continue;
                }

                $value = self::readPropertyValue($manager, $property);
                self::writePropertyValue($instance, $property, $value);
            }
        }

        if (! $currentCartProperty->isInitialized($instance)) {
            throw new RuntimeException('Failed to initialize CartManager proxy current cart instance.');
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

    private static function resolveProperty(ReflectionClass $class, string $name): ?ReflectionProperty
    {
        while ($class !== false) {
            try {
                return $class->getProperty($name);
            } catch (ReflectionException $e) {
                $class = $class->getParentClass();
            }
        }

        return null;
    }

    private static function ensurePropertyInitialized(object $object, ReflectionProperty $property): void
    {
        if ($property->isInitialized($object)) {
            return;
        }

        try {
            if ($property->getName() === 'currentCart' && method_exists($object, 'getCurrentCart')) {
                $object->getCurrentCart();
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to initialize CartManager current cart.', 0, $e);
        }

        if (! $property->isInitialized($object)) {
            throw new RuntimeException('CartManager current cart remains uninitialized.');
        }
    }

    /**
     * @return iterable<ReflectionClass>
     */
    private static function walkClassHierarchy(ReflectionClass $class): iterable
    {
        while ($class !== false) {
            yield $class;
            $class = $class->getParentClass();
        }
    }

    private static function readPropertyValue(object $object, ReflectionProperty $property): mixed
    {
        $reader = Closure::bind(static function (object $instance, string $name) {
            return $instance->{$name};
        }, null, $property->getDeclaringClass()->getName());

        return $reader($object, $property->getName());
    }

    private static function writePropertyValue(object $object, ReflectionProperty $property, mixed $value): void
    {
        $writer = Closure::bind(static function (object $instance, string $name, mixed $val): void {
            $instance->{$name} = $val;
        }, null, $property->getDeclaringClass()->getName());

        $writer($object, $property->getName(), $value);
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
