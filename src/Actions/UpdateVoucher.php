<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateVoucher
{
    use AsAction;
    use NormalizesVoucherCodes;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(string $code, array $data): VoucherModel
    {
        $normalizedCode = $this->normalizeCode($code);

        $voucher = VoucherModel::query()
            ->where('code', $normalizedCode)
            ->firstOrFail();

        if (isset($data['code'])) {
            $data['code'] = $this->normalizeCode((string) $data['code']);
        }

        $voucher->update($data);

        $fresh = $voucher->fresh();

        return $fresh ?? $voucher;
    }
}
