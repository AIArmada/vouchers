<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Cart\Cart;
use AIArmada\Cart\CartManager;
use AIArmada\Cart\Contracts\CartManagerInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * CartManager decorator that adds voucher functionality.
 *
 * Uses composition pattern to wrap any CartManagerInterface implementation,
 * enabling stacking with other decorators (e.g., CartManagerWithAffiliates).
 */
final class CartManagerWithVouchers implements CartManagerInterface
{
    public function __construct(
        private CartManagerInterface $manager
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists(CartWithVouchers::class, $method)) {
            $wrapper = new CartWithVouchers($this->getCurrentCart());

            return $wrapper->{$method}(...$arguments);
        }

        return $this->manager->{$method}(...$arguments);
    }

    public static function fromCartManager(CartManagerInterface $manager): self
    {
        if ($manager instanceof self) {
            return $manager;
        }

        return new self($manager);
    }

    /**
     * Get the underlying CartManager (unwraps all decorators if needed)
     */
    public function getBaseManager(): CartManagerInterface
    {
        if ($this->manager instanceof self) {
            return $this->manager->getBaseManager();
        }

        return $this->manager;
    }

    public function getCurrentCart(): Cart
    {
        return $this->ensureVoucherRulesFactory($this->manager->getCurrentCart());
    }

    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        $cart = $this->manager->getCartInstance($name, $identifier);

        return $this->ensureVoucherRulesFactory($cart);
    }

    public function instance(): string
    {
        return $this->manager->instance();
    }

    public function setInstance(string $name): static
    {
        $this->manager->setInstance($name);

        return $this;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->manager->setIdentifier($identifier);

        return $this;
    }

    public function forgetIdentifier(): static
    {
        $this->manager->forgetIdentifier();

        return $this;
    }

    public function forOwner(Model $owner): static
    {
        return new self($this->manager->forOwner($owner));
    }

    public function getOwnerType(): ?string
    {
        return $this->manager->getOwnerType();
    }

    public function getOwnerId(): string | int | null
    {
        return $this->manager->getOwnerId();
    }

    public function getById(string $uuid): ?Cart
    {
        $cart = $this->manager->getById($uuid);

        if ($cart === null) {
            return null;
        }

        return $this->ensureVoucherRulesFactory($cart);
    }

    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        return $this->manager->swap($oldIdentifier, $newIdentifier, $instance);
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
