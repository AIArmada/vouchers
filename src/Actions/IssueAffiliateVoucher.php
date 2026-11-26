<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleRecord;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateVoucherIssuer;
use AIArmada\Vouchers\Models\Voucher;

final class IssueAffiliateVoucher implements AffiliateVoucherIssuer
{
    public function connectionName(): string
    {
        return (string) (new Voucher)->getConnection()->getName();
    }

    public function issue(AffiliateLifecycleRecord $affiliate): ?string
    {
        $reference = $affiliate->reference;

        if (Voucher::query()->withoutOwnerScope()
            ->where('affiliate_id', $reference->id)
            ->where('owner_type', $reference->ownerType)
            ->where('owner_id', $reference->ownerId)
            ->exists()) {
            return null;
        }

        $defaults = (array) config('vouchers.affiliates.voucher_defaults', []);

        $voucher = Voucher::create([
            'code' => $this->code($affiliate->code),
            'name' => sprintf('%s Referral Discount', $affiliate->name),
            'description' => sprintf('Referral discount code for affiliate %s', $affiliate->code),
            'type' => $defaults['type'] ?? 'percentage',
            'value' => $defaults['value'] ?? 1000,
            'currency' => $defaults['currency'] ?? config('vouchers.default_currency', 'MYR'),
            'status' => $defaults['status'] ?? 'active',
            'affiliate_id' => $reference->id,
            'owner_type' => $reference->ownerType,
            'owner_id' => $reference->ownerId,
            'metadata' => ['auto_generated' => true],
        ]);

        return $voucher->code;
    }

    private function code(string $affiliateCode): string
    {
        $prefix = (string) config('vouchers.affiliates.code_prefix', 'REF');

        return match (config('vouchers.affiliates.code_format', 'prefix_code')) {
            'code_only' => mb_strtoupper($affiliateCode),
            'prefix_random' => mb_strtoupper($prefix . bin2hex(random_bytes(4))),
            default => mb_strtoupper($prefix . $affiliateCode),
        };
    }
}
