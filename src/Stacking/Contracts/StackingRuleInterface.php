<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Stacking\StackingDecision;
use Illuminate\Support\Collection;

/**
 * Interface for stacking rules that determine voucher compatibility.
 *
 * Each rule evaluates whether a new voucher can be added to a cart
 * that already has other vouchers applied.
 */
interface StackingRuleInterface
{
    /**
     * Evaluate whether a voucher can be added given the current cart state.
     *
     * @param  VoucherCondition  $newVoucher  The voucher attempting to be added
     * @param  Collection<int, VoucherCondition>  $existingVouchers  Currently applied vouchers
     * @param  Cart  $cart  The cart being evaluated
     * @param  array<string, mixed>  $config  Rule-specific configuration
     * @return StackingDecision The decision whether to allow or deny stacking
     */
    public function evaluate(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart,
        array $config
    ): StackingDecision;

    /**
     * Get the rule type identifier.
     */
    public function getType(): string;

    /**
     * Get the priority of this rule (lower = evaluated first).
     */
    public function getPriority(): int;
}
