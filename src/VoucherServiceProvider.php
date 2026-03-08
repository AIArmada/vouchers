<?php

declare(strict_types=1);

namespace AIArmada\Vouchers;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Facades\Cart as CartFacade;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Vouchers\Cart\VoucherConditionProvider;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Listeners\IncrementVoucherAppliedCount;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\Services\VoucherValidator;
use AIArmada\Vouchers\Support\AffiliateIntegrationRegistrar;
use AIArmada\Vouchers\Support\CartManagerWithVouchers;
use AIArmada\Vouchers\Support\VoucherRulesFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class VoucherServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('vouchers')
            ->hasConfigFile()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(VoucherService::class);
        $this->app->singleton(VoucherValidator::class);
        $this->app->singleton(VoucherRulesFactory::class, static fn () => new VoucherRulesFactory);
        $this->app->singleton(AffiliateIntegrationRegistrar::class);

        // Bind interface for checkout package integration
        $this->app->bind(VoucherServiceInterface::class, VoucherService::class);

        if (class_exists(ConditionProviderRegistry::class)) {
            $this->app->singleton(VoucherConditionProvider::class);
        }

        $this->app->resolving(CartConditionResolver::class, function (CartConditionResolver $resolver): void {
            $resolver->register(function (mixed $payload) {
                if ($payload instanceof VoucherCondition) {
                    $cartCondition = $payload->toCartCondition();

                    return $payload->isDynamic() ? $cartCondition->withoutRules() : $cartCondition;
                }

                if ($payload instanceof VoucherData) {
                    return (new VoucherCondition($payload, dynamic: false))
                        ->toCartCondition();
                }

                if (is_array($payload)) {
                    $code = $payload['voucher_code'] ?? $payload['code'] ?? null;

                    if (is_string($code) && $code !== '' && ($voucherData = Voucher::find($code))) {
                        /** @var int $conditionOrder */
                        $conditionOrder = config('vouchers.cart.condition_order', 50);
                        $order = isset($payload['order']) && is_int($payload['order'])
                            ? $payload['order']
                            : $conditionOrder;

                        return (new VoucherCondition($voucherData, $order, dynamic: false))
                            ->toCartCondition();
                    }
                }

                if (is_string($payload) && str_starts_with($payload, 'voucher:')) {
                    $code = mb_substr($payload, 8);

                    if ($code !== '' && ($voucherData = Voucher::find($code))) {
                        return (new VoucherCondition($voucherData, dynamic: false))
                            ->toCartCondition();
                    }
                }

                return null;
            }, 100);
        });

        $this->app->bind('voucher', VoucherService::class);
    }

    public function packageBooted(): void
    {
        Event::listen(VoucherApplied::class, IncrementVoucherAppliedCount::class);

        $this->app->make(AffiliateIntegrationRegistrar::class)->register();

        if (class_exists(ConditionProviderRegistry::class)) {
            $this->app->make(ConditionProviderRegistry::class)
                ->register(VoucherConditionProvider::class);
        }

        $this->app->extend('cart', function (CartManagerInterface $manager, $app): CartManagerInterface {
            if ($manager instanceof CartManagerWithVouchers) {
                return $manager;
            }

            $proxy = CartManagerWithVouchers::fromCartManager($manager);

            /** @var Application $app */
            $app->instance(CartManager::class, $proxy);
            $app->instance(CartManagerInterface::class, $proxy);

            CartFacade::clearResolvedInstance('cart');

            return $proxy;
        });
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            VoucherService::class,
            VoucherServiceInterface::class,
            VoucherValidator::class,
            VoucherRulesFactory::class,
            AffiliateIntegrationRegistrar::class,
            'voucher',
        ];
    }
}
