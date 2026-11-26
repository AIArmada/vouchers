<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Affiliates\Events\AffiliateActivated;
use AIArmada\Affiliates\Events\AffiliateCreated;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateVoucherProvisioning;
use AIArmada\Vouchers\Actions\IssueAffiliateVoucher;
use Illuminate\Contracts\Events\Dispatcher;

final class AffiliateIntegrationRegistrar
{
    public function __construct(private readonly Dispatcher $events) {}

    public function register(): void
    {
        if (! class_exists(AffiliateCreated::class) || ! app()->bound(AffiliateVoucherProvisioning::class)) {
            return;
        }

        if (! config('vouchers.affiliates.enabled', true)) {
            return;
        }

        if (config('vouchers.affiliates.auto_create_voucher', false)) {
            $this->events->listen(AffiliateCreated::class, function (AffiliateCreated $event): void {
                app(AffiliateVoucherProvisioning::class)->provision(
                    $event->affiliate,
                    app(IssueAffiliateVoucher::class),
                    requireActive: false,
                    setDefault: (bool) config('vouchers.affiliates.set_default_voucher_code', true),
                );
            });
        }

        if (config('vouchers.affiliates.create_on_activation', true)) {
            $this->events->listen(AffiliateActivated::class, function (AffiliateActivated $event): void {
                app(AffiliateVoucherProvisioning::class)->provision(
                    $event->affiliate,
                    app(IssueAffiliateVoucher::class),
                    requireActive: true,
                    setDefault: (bool) config('vouchers.affiliates.set_default_voucher_code', true),
                );
            });
        }
    }
}
