<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Stacking\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Stacking\Enums\StackingMode;
use AIArmada\Vouchers\Stacking\StackingDecision;
use Illuminate\Support\Collection;

/**
 * Interface for stacking policies that orchestrate voucher stacking behavior.
 *
 * A stacking policy defines the overall stacking mode and contains
 * multiple rules that determine voucher compatibility.
 */
interface StackingPolicyInterface
{
    /**
     * Check if a voucher can be added given existing vouchers.
     *
     * @param  VoucherCondition  $newVoucher  The voucher to add
     * @param  Collection<int, VoucherCondition>  $existingVouchers  Currently applied vouchers
     * @param  Cart  $cart  The cart being modified
     * @return StackingDecision Whether the voucher can be added
     */
    public function canAdd(
        VoucherCondition $newVoucher,
        Collection $existingVouchers,
        Cart $cart
    ): StackingDecision;

    /**
     * Resolve conflicts when maximum vouchers exceeded.
     *
     * Returns the vouchers that should remain after conflict resolution.
     * This may involve removing the oldest, lowest-value, or lowest-priority vouchers.
     *
     * @param  Collection<int, VoucherCondition>  $vouchers  All vouchers including the new one
     * @param  Cart  $cart  The cart being modified
     * @return Collection<int, VoucherCondition> The vouchers that should remain
     */
    public function resolveConflict(
        Collection $vouchers,
        Cart $cart
    ): Collection;

    /**
     * Calculate optimal application order for vouchers.
     *
     * For sequential stacking, the order affects the final discount.
     * Returns vouchers sorted by application order.
     *
     * @param  Collection<int, VoucherCondition>  $vouchers  Vouchers to order
     * @param  Cart  $cart  The cart being modified
     * @return Collection<int, VoucherCondition> Vouchers in optimal application order
     */
    public function getApplicationOrder(
        Collection $vouchers,
        Cart $cart
    ): Collection;

    /**
     * Get the stacking mode for this policy.
     */
    public function getMode(): StackingMode;

    /**
     * Get all rules configured in this policy.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRules(): array;

    /**
     * Check if auto-optimization is enabled.
     */
    public function isAutoOptimizeEnabled(): bool;

    /**
     * Check if auto-replace is enabled when conflicts occur.
     */
    public function isAutoReplaceEnabled(): bool;
}
