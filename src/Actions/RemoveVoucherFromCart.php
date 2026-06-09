<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Events\VoucherRemoved;
use AIArmada\Vouchers\Facades\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

final class RemoveVoucherFromCart
{
    use AsAction;

    public function handle(Cart $cart, string $code): void
    {
        $voucherCondition = $this->findVoucherCondition($cart, $code);

        if ($voucherCondition === null) {
            return;
        }

        $conditionName = $voucherCondition->getName();

        if ($cart->getDynamicConditions()->has($conditionName)) {
            $cart->removeDynamicCondition($conditionName);
        } else {
            $cart->removeCondition($conditionName);
        }

        $voucherData = Voucher::find($code);

        if ($voucherData !== null) {
            VoucherRemoved::dispatch($cart, $voucherData);
        }
    }

    private function findVoucherCondition(Cart $cart, string $code): ?VoucherCondition
    {
        $normalized = $this->normalizeCode($code);

        $collections = [
            $cart->getDynamicConditions(),
            $cart->getConditions(),
        ];

        foreach ($collections as $collection) {
            foreach ($collection as $condition) {
                if ($condition instanceof VoucherCondition) {
                    if ($this->normalizeCode($condition->getVoucherCode()) === $normalized) {
                        return $condition;
                    }
                } elseif ($condition instanceof CartCondition && $condition->getType() === 'voucher') {
                    $voucherCondition = VoucherCondition::fromCartCondition($condition);

                    if ($voucherCondition !== null && $this->normalizeCode($voucherCondition->getVoucherCode()) === $normalized) {
                        return $voucherCondition;
                    }
                }
            }
        }

        return null;
    }

    private function normalizeCode(string $code): string
    {
        $normalized = mb_trim($code);

        if (config('vouchers.code.auto_uppercase', true)) {
            $normalized = mb_strtoupper($normalized);
        }

        return $normalized;
    }
}
