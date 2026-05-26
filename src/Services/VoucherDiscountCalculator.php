<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Services;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Compound\Conditions\CompoundVoucherCondition;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;

final class VoucherDiscountCalculator
{
    public function calculate(VoucherData $voucher, int $subtotal, ?Cart $cart = null): int
    {
        if (! $voucher->type->appliesAtCheckout()) {
            return 0;
        }

        if ($voucher->isCompound()) {
            if (! $cart instanceof Cart) {
                return 0;
            }

            $condition = CompoundVoucherCondition::create($voucher, dynamic: false);

            if ($condition === null) {
                return 0;
            }

            return max(0, $condition->calculateDiscount($cart));
        }

        $condition = new VoucherCondition($voucher, dynamic: false);

        return (int) round(abs($condition->getCalculatedValue((float) $subtotal)));
    }
}
