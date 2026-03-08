<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\States\Active;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new voucher.
 */
final class CreateVoucher
{
    use AsAction;
    use NormalizesVoucherCodes;

    /**
     * Create a new voucher with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): VoucherModel
    {
        return DB::transaction(function () use ($data): VoucherModel {
            $code = $data['code'] ?? $this->generateCode();
            $normalizedCode = $this->normalizeCode($code);

            $createData = [
                'code' => $normalizedCode,
                'name' => $data['name'] ?? $normalizedCode,
                'type' => $data['type'],
                'value' => $data['value'],
                'currency' => $data['currency'] ?? config('vouchers.default_currency', 'MYR'),
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? Active::class,
                'usage_limit' => $data['max_uses'] ?? $data['usage_limit'] ?? null,
                'usage_limit_per_user' => $data['max_uses_per_user'] ?? $data['usage_limit_per_user'] ?? null,
                'min_cart_value' => $data['min_order_value'] ?? $data['min_cart_value'] ?? null,
                'max_discount' => $data['max_discount_value'] ?? $data['max_discount'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'target_definition' => $data['target_definition'] ?? null,
                'stacking_rules' => $data['stacking_rules'] ?? null,
                'exclusion_groups' => $data['exclusion_groups'] ?? null,
                'stacking_priority' => $data['stacking_priority'] ?? 100,
                'allows_manual_redemption' => $data['allows_manual_redemption'] ?? false,
            ];

            // Handle owner assignment
            if (isset($data['owner_type'], $data['owner_id'])) {
                $createData['owner_type'] = $data['owner_type'];
                $createData['owner_id'] = $data['owner_id'];
            } elseif (
                config('vouchers.owner.enabled', false)
                && config('vouchers.owner.auto_assign_on_create', true)
            ) {
                $owner = OwnerContext::resolve();
                if ($owner !== null) {
                    $createData['owner_type'] = $owner->getMorphClass();
                    $createData['owner_id'] = $owner->getKey();
                }
            }

            return VoucherModel::create($createData);
        });
    }

    private function generateCode(): string
    {
        /** @var string $prefix */
        $prefix = (string) config('vouchers.code.prefix', '');
        $length = (int) config('vouchers.code.length', 8);

        do {
            $code = $this->normalizeCode($prefix . Str::random($length));
        } while (VoucherModel::where('code', $code)->exists());

        return $code;
    }
}
