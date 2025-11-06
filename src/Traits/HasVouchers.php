<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Traits;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Events\VoucherRemoved;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Support\CartWithVouchers;
use AIArmada\Vouchers\Support\VoucherRulesFactory;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * HasVouchers trait adds voucher management capabilities to the Cart.
 *
 * This trait provides convenient methods for applying, removing, and checking vouchers
 * while integrating seamlessly with the cart's condition system.
 */
trait HasVouchers
{
    /**
     * Apply a voucher to the cart by code.
     *
     * This method validates the voucher, creates a VoucherCondition,
     * and adds it to the cart's conditions.
     *
     * @param  string  $code  The voucher code to apply
     * @param  int  $order  The order in which the voucher condition should be applied (default: 100)
     *
     * @throws InvalidVoucherException If the voucher is invalid or cannot be applied
     */
    public function applyVoucher(string $code, int $order = 100): self
    {
        $cart = $this->getUnderlyingCart();

        $validationResult = Voucher::validate($code, $cart);

        if (! $validationResult->isValid) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' cannot be applied: {$validationResult->reason}"
            );
        }

        if ($this->hasVoucher($code)) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' is already applied to this cart"
            );
        }

        $maxVouchers = config('vouchers.cart.max_vouchers_per_cart', 1);
        $currentVoucherCount = count($this->getAppliedVouchers());

        if ($currentVoucherCount >= $maxVouchers && $maxVouchers > 0) {
            $replaceWhenMaxReached = config('vouchers.cart.replace_when_max_reached', false);

            if ($replaceWhenMaxReached) {
                // Clear existing vouchers to make room for the new one
                $this->clearVouchers();
            } else {
                throw new InvalidVoucherException(
                    "Cart already has the maximum number of vouchers ({$maxVouchers})"
                );
            }
        }

        $voucherData = Voucher::find($code);

        if ($voucherData === null) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' not found"
            );
        }

        $this->ensureVoucherRulesFactory($cart);

        $voucherCondition = new VoucherCondition($voucherData, $order);

        try {
            $cart->registerDynamicCondition(
                $voucherCondition->toCartCondition(),
                null,
                $voucherCondition->getRuleFactoryKey(),
                $voucherCondition->getRuleFactoryContext()
            );
        } catch (Throwable $exception) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' cannot be applied: {$exception->getMessage()}",
                previous: $exception
            );
        }

        if ($this instanceof CartWithVouchers) {
            Event::dispatch(new VoucherApplied($cart, $voucherData));
        }

        return $this;
    }

    /**
     * Remove a voucher from the cart by code.
     *
     * @param  string  $code  The voucher code to remove
     */
    public function removeVoucher(string $code): self
    {
        $voucherCondition = $this->getVoucherCondition($code);

        if ($voucherCondition === null) {
            return $this;
        }

        $cart = $this->getUnderlyingCart();
        $conditionName = $voucherCondition->getName();

        if ($cart->getDynamicConditions()->has($conditionName)) {
            $cart->removeDynamicCondition($conditionName);
        } else {
            $cart->removeCondition($conditionName);
        }

        if ($this instanceof CartWithVouchers) {
            Event::dispatch(new VoucherRemoved($cart, $voucherCondition->getVoucher()));
        }

        return $this;
    }

    /**
     * Remove all vouchers from the cart.
     */
    public function clearVouchers(): self
    {
        foreach ($this->getAppliedVouchers() as $voucher) {
            $this->removeVoucher($voucher->getVoucherCode());
        }

        return $this;
    }

    /**
     * Check if the cart has a specific voucher applied, or any voucher if code is null.
     *
     * @param  string|null  $code  Optional voucher code to check for. If null, checks for any voucher.
     */
    public function hasVoucher(?string $code = null): bool
    {
        if ($code === null) {
            return count($this->getAppliedVouchers()) > 0;
        }

        $normalized = $this->normalizeVoucherCode($code);

        foreach ($this->getAppliedVouchers() as $voucher) {
            if ($this->normalizeVoucherCode($voucher->getVoucherCode()) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a specific voucher condition by code.
     *
     * @param  string  $code  The voucher code
     */
    public function getVoucherCondition(string $code): ?VoucherCondition
    {
        $normalized = $this->normalizeVoucherCode($code);
        $conditions = $this->collectVoucherConditions();

        return $conditions[$normalized] ?? null;
    }

    /**
     * Get all applied voucher conditions.
     *
     * @return array<VoucherCondition>
     */
    public function getAppliedVouchers(): array
    {
        return array_values($this->collectVoucherConditions());
    }

    /**
     * Get the codes of all applied vouchers.
     *
     * @return array<string>
     */
    public function getAppliedVoucherCodes(): array
    {
        return array_map(
            fn (VoucherCondition $voucher) => $voucher->getVoucherCode(),
            $this->getAppliedVouchers()
        );
    }

    /**
     * Calculate the total discount from all applied vouchers.
     *
     * @return float The total voucher discount amount
     */
    public function getVoucherDiscount(): float
    {
        $discount = 0.0;
        $cart = $this->getUnderlyingCart();
        $subtotalMoney = $cart->subtotal();
        $baseValue = (float) $subtotalMoney->getAmount();

        foreach ($this->getAppliedVouchers() as $voucher) {
            $discountAmount = abs($voucher->getCalculatedValue($baseValue));
            $discount += $discountAmount;

            // Update subtotal for next voucher calculation if stacking
            if (config('vouchers.cart.allow_stacking', false)) {
                $baseValue -= $discountAmount;
            }
        }

        return $discount;
    }

    /**
     * Check if the cart can accept more vouchers.
     */
    public function canAddVoucher(): bool
    {
        $maxVouchers = config('vouchers.cart.max_vouchers_per_cart', 1);

        if ($maxVouchers === 0) {
            return false; // Vouchers disabled
        }

        $currentVoucherCount = count($this->getAppliedVouchers());

        return $currentVoucherCount < $maxVouchers || $maxVouchers === -1;
    }

    /**
     * Validate all currently applied vouchers and remove invalid ones.
     *
     * This is useful to call after cart modifications to ensure all vouchers
     * are still valid (e.g., minimum cart value still met).
     *
     * @return array<string> Array of voucher codes that were removed
     */
    public function validateAppliedVouchers(): array
    {
        $removedVouchers = [];

        foreach ($this->getAppliedVouchers() as $voucherCondition) {
            $code = $voucherCondition->getVoucherCode();
            $validationResult = Voucher::validate($code, $this->getUnderlyingCart());

            if (! $validationResult->isValid) {
                $this->removeVoucher($code);
                $removedVouchers[] = $code;
            }
        }

        return $removedVouchers;
    }

    private function getUnderlyingCart(): Cart
    {
        if ($this instanceof CartWithVouchers) {
            return $this->getCart();
        }

        return $this;
    }

    private function ensureVoucherRulesFactory(Cart $cart): void
    {
        $currentFactory = $cart->getRulesFactory();

        if ($currentFactory instanceof VoucherRulesFactory) {
            return;
        }

        if ($currentFactory === null) {
            $cart->withRulesFactory(app(VoucherRulesFactory::class));

            return;
        }

        $cart->withRulesFactory(new VoucherRulesFactory($currentFactory));
    }

    private function normalizeVoucherCode(string $code): string
    {
        $normalized = mb_trim($code);

        if (config('vouchers.code.auto_uppercase', true)) {
            $normalized = mb_strtoupper($normalized);
        }

        return $normalized;
    }

    /**
     * @return array<string, VoucherCondition>
     */
    private function collectVoucherConditions(): array
    {
        $cart = $this->getUnderlyingCart();

        $collections = [
            $cart->getDynamicConditions(),
            $cart->getConditions(),
        ];

        $conditions = [];

        foreach ($collections as $collection) {
            foreach ($collection as $condition) {
                if ($condition instanceof VoucherCondition) {
                    $voucherCondition = $condition;
                } elseif ($condition instanceof CartCondition && $condition->getType() === 'voucher') {
                    $voucherCondition = VoucherCondition::fromCartCondition($condition);

                    if ($voucherCondition === null) {
                        continue;
                    }
                } else {
                    continue;
                }

                $conditions[$this->normalizeVoucherCode($voucherCondition->getVoucherCode())] = $voucherCondition;
            }
        }

        return $conditions;
    }
}
