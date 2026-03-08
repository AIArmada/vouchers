<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Affiliates\Events\AffiliateActivated;
use AIArmada\Affiliates\Events\AffiliateCreated;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Registers affiliate integration when aiarmada/affiliates is installed.
 *
 * Provides bidirectional linking between vouchers and affiliates:
 * - Auto-create voucher codes when affiliates are created
 * - Link vouchers to their owning affiliate
 * - Track voucher performance per affiliate
 */
final class AffiliateIntegrationRegistrar
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function register(): void
    {
        if (! class_exists(AffiliateCreated::class)) {
            return;
        }

        if (! config('vouchers.affiliates.enabled', true)) {
            return;
        }

        $this->registerAffiliateCreatedListener();
        $this->registerAffiliateActivatedListener();
    }

    /**
     * Listen for affiliate creation to auto-create voucher codes.
     */
    private function registerAffiliateCreatedListener(): void
    {
        if (! config('vouchers.affiliates.auto_create_voucher', false)) {
            return;
        }

        $this->events->listen(
            AffiliateCreated::class,
            function (object $event): void {
                $this->createVoucherForAffiliate($event->affiliate);
            }
        );
    }

    /**
     * Listen for affiliate activation to auto-create voucher codes.
     */
    private function registerAffiliateActivatedListener(): void
    {
        if (! config('vouchers.affiliates.create_on_activation', true)) {
            return;
        }

        $this->events->listen(
            AffiliateActivated::class,
            function (object $event): void {
                $affiliate = $event->affiliate;

                // Only create if affiliate doesn't already have a voucher
                if ($this->affiliateHasVoucher($affiliate)) {
                    return;
                }

                $this->createVoucherForAffiliate($affiliate);
            }
        );
    }

    /**
     * Create a voucher code for an affiliate.
     *
     * @param  Affiliate  $affiliate
     */
    private function createVoucherForAffiliate(object $affiliate): void
    {
        /** @var array<string, mixed> $voucherConfig */
        $voucherConfig = config('vouchers.affiliates.voucher_defaults', []);

        $code = $this->generateAffiliateVoucherCode($affiliate);

        $voucher = Voucher::create([
            'code' => $code,
            'name' => sprintf('%s Referral Discount', $affiliate->name),
            'description' => sprintf('Referral discount code for affiliate %s', $affiliate->code),
            'type' => $voucherConfig['type'] ?? 'percentage',
            'value' => $voucherConfig['value'] ?? 1000, // 10% default
            'currency' => $voucherConfig['currency'] ?? config('vouchers.default_currency', 'MYR'),
            'status' => $voucherConfig['status'] ?? 'active',
            'affiliate_id' => $affiliate->id,
            'owner_type' => $affiliate->owner_type,
            'owner_id' => $affiliate->owner_id,
            'metadata' => [
                'affiliate_code' => $affiliate->code,
                'affiliate_id' => $affiliate->id,
                'auto_generated' => true,
            ],
        ]);

        // Update affiliate with default voucher code if configured
        if (config('vouchers.affiliates.set_default_voucher_code', true)) {
            $affiliate->update(['default_voucher_code' => $voucher->code]);
        }
    }

    /**
     * Generate a voucher code for an affiliate.
     */
    private function generateAffiliateVoucherCode(object $affiliate): string
    {
        /** @var string $prefix */
        $prefix = config('vouchers.affiliates.code_prefix', 'REF');

        /** @var string $format */
        $format = config('vouchers.affiliates.code_format', 'prefix_code');

        return match ($format) {
            'prefix_code' => mb_strtoupper($prefix . $affiliate->code),
            'code_only' => mb_strtoupper($affiliate->code),
            'prefix_random' => mb_strtoupper($prefix . bin2hex(random_bytes(4))),
            default => mb_strtoupper($prefix . $affiliate->code),
        };
    }

    /**
     * Check if affiliate already has a voucher.
     */
    private function affiliateHasVoucher(object $affiliate): bool
    {
        return Voucher::where('affiliate_id', $affiliate->id)->exists();
    }
}
