<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Filament\Integrations;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\Vouchers\Exceptions\VoucherException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FilamentCartBridge
{
    private bool $available;

    private bool $warmed = false;

    /** @var class-string<Model>|null */
    private ?string $cartModel = null;

    /** @var class-string|null */
    private ?string $cartResource = null;

    public function __construct()
    {
        $this->available = class_exists(Cart::class) && class_exists(CartResource::class);

        if ($this->available) {
            $this->cartModel = Cart::class;
            $this->cartResource = CartResource::class;
        }
    }

    private function isOwnerEnabled(): bool
    {
        return (bool) config('vouchers.owner.enabled', false);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopeForOwner(Builder $query): Builder
    {
        if (! $this->isOwnerEnabled()) {
            return $query;
        }

        return OwnerQuery::applyToEloquentBuilder(
            $query,
            OwnerContext::resolve(),
            (bool) config('vouchers.owner.include_global', false),
        );
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function isWarmed(): bool
    {
        return $this->warmed;
    }

    public function warm(): void
    {
        if ($this->warmed || ! $this->available) {
            return;
        }

        if (! class_exists(CartInstanceManager::class)) {
            Log::warning('FilamentCartBridge: CartInstanceManager not found, some features may be limited');
        }

        $this->warmed = true;
    }

    /**
     * @return class-string<Model>|null
     */
    public function getCartModel(): ?string
    {
        return $this->cartModel;
    }

    /**
     * @return class-string|null
     */
    public function getCartResource(): ?string
    {
        return $this->cartResource;
    }

    public function resolveCartUrl(?string $identifier): ?string
    {
        if (! $this->available || $identifier === null || $identifier === '') {
            return null;
        }

        $model = $this->getCartModel();
        $resource = $this->getCartResource();

        if (! $model || ! $resource) {
            return null;
        }

        try {
            $query = $model::query();
            $query = $this->scopeForOwner($query);

            /** @var Model|null $cart */
            $cart = $query
                ->where('identifier', $identifier)
                ->latest('created_at')
                ->first();

            if (! $cart instanceof Model) {
                return null;
            }

            return $resource::getUrl('view', ['record' => $cart]);
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to resolve cart URL', [
                'identifier' => $identifier,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function findCart(string $cartId): ?Model
    {
        if (! $this->available) {
            return null;
        }

        $model = $this->getCartModel();

        if (! $model) {
            return null;
        }

        try {
            $query = $model::query();

            return $this->scopeForOwner($query)->find($cartId);
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to find cart', [
                'cart_id' => $cartId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function getCartInstance(Model $cart): ?object
    {
        if (! $this->available || ! class_exists(CartInstanceManager::class)) {
            return null;
        }

        if (! $this->isCartVisibleToCurrentOwner($cart)) {
            return null;
        }

        try {
            /** @var Cart $cart */
            return app(CartInstanceManager::class)->resolve(
                $cart->instance,
                $cart->identifier
            );
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to get cart instance', [
                'cart_id' => $cart->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return Collection<int, object>
     */
    public function getAppliedVouchers(Model $cart): Collection
    {
        $instance = $this->getCartInstance($cart);

        if (! $instance) {
            return collect();
        }

        try {
            $vouchers = $instance->getAppliedVouchers();

            if (! is_iterable($vouchers)) {
                return collect();
            }

            /** @var iterable<int, object> $vouchers */
            return collect($vouchers);
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to get applied vouchers', [
                'cart_id' => $cart->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * @throws VoucherException
     */
    public function applyVoucher(Model $cart, string $code): bool
    {
        if (! $this->isCartVisibleToCurrentOwner($cart)) {
            throw new VoucherException('You are not authorized to modify this cart');
        }

        $instance = $this->getCartInstance($cart);

        if (! $instance) {
            throw new VoucherException('Cart integration is not available');
        }

        $instance->applyVoucher($code);

        return true;
    }

    /**
     * @throws VoucherException
     */
    public function removeVoucher(Model $cart, string $code): bool
    {
        if (! $this->isCartVisibleToCurrentOwner($cart)) {
            throw new VoucherException('You are not authorized to modify this cart');
        }

        $instance = $this->getCartInstance($cart);

        if (! $instance) {
            throw new VoucherException('Cart integration is not available');
        }

        $instance->removeVoucher($code);

        return true;
    }

    public function hasVoucher(Model $cart, string $code): bool
    {
        if (! $this->isCartVisibleToCurrentOwner($cart)) {
            return false;
        }

        return $this->getAppliedVouchers($cart)
            ->contains(fn (object $voucher): bool => ($voucher->code ?? '') === $code);
    }

    public function countCartsWithVoucher(string $voucherCode): int
    {
        if (! $this->available) {
            return 0;
        }

        $model = $this->getCartModel();

        if (! $model) {
            return 0;
        }

        try {
            $escapedCode = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $voucherCode);

            /** @var Builder<Model> $query */
            $query = $model::query();
            $query = $this->scopeForOwner($query);

            return $query
                ->whereNotNull('conditions')
                ->where(function ($q) use ($voucherCode, $escapedCode): void {
                    $q->whereJsonContains('conditions', ['voucher' => $voucherCode])
                        ->orWhereRaw('conditions LIKE ?', ['%"code":"' . $escapedCode . '"%']);
                })
                ->count();
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to count carts with voucher', [
                'voucher_code' => $voucherCode,
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @return array{active_carts_with_vouchers: int, total_potential_discount: int}
     */
    public function getVoucherCartStats(): array
    {
        if (! $this->available) {
            return [
                'active_carts_with_vouchers' => 0,
                'total_potential_discount' => 0,
            ];
        }

        $model = $this->getCartModel();

        if (! $model) {
            return [
                'active_carts_with_vouchers' => 0,
                'total_potential_discount' => 0,
            ];
        }

        try {
            /** @var Builder<Model> $query */
            $query = $model::query();
            $query = $this->scopeForOwner($query);

            $cartsWithVouchers = $query
                ->whereNotNull('conditions')
                ->whereRaw("conditions LIKE '%voucher%'")
                ->count();

            return [
                'active_carts_with_vouchers' => $cartsWithVouchers,
                'total_potential_discount' => 0,
            ];
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to get voucher cart stats', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'active_carts_with_vouchers' => 0,
                'total_potential_discount' => 0,
            ];
        }
    }

    private function isCartVisibleToCurrentOwner(Model $cart): bool
    {
        if (! $this->isOwnerEnabled()) {
            return true;
        }

        $cartClass = $cart::class;

        /** @var Builder<Model> $query */
        $query = $cartClass::query();
        $query = $this->scopeForOwner($query);

        return $query->whereKey($cart->getKey())->exists();
    }
}
