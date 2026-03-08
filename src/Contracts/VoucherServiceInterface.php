<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Contracts;

use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Core voucher service contract.
 */
interface VoucherServiceInterface
{
    /**
     * Find a voucher by code.
     */
    public function find(string $code): ?VoucherData;

    /**
     * Find a voucher by code or throw exception.
     */
    public function findOrFail(string $code): VoucherData;

    /**
     * Create a new voucher.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): VoucherData;

    /**
     * Update an existing voucher.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $code, array $data): VoucherData;

    /**
     * Delete a voucher.
     */
    public function delete(string $code): bool;

    /**
     * Validate a voucher for checkout.
     *
     * @param  mixed  $cart  Cart object or context array
     * @return array{valid: bool, message: string|null, voucher: array<string, mixed>|null}|VoucherValidationResult
     */
    public function validate(string $code, mixed $cart): array | VoucherValidationResult;

    /**
     * Check if a voucher code is valid.
     */
    public function isValid(string $code): bool;

    /**
     * Check if a voucher can be used by a specific user.
     */
    public function canBeUsedBy(string $code, ?Model $user = null): bool;

    /**
     * Get remaining uses for a voucher.
     */
    public function getRemainingUses(string $code): int;

    /**
     * Record voucher usage after redemption.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordUsage(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null
    ): void;

    /**
     * Get usage history for a voucher.
     *
     * @return EloquentCollection<int, VoucherUsage>
     */
    public function getUsageHistory(string $code): EloquentCollection;

    /**
     * Add a voucher to a user's wallet.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function addToWallet(string $code, Model $holder, ?array $metadata = null): VoucherWallet;

    /**
     * Remove a voucher from a user's wallet.
     */
    public function removeFromWallet(string $code, Model $holder): bool;

    /**
     * Reserve a voucher for a checkout session.
     */
    public function reserve(string $code, string $sessionId): void;

    /**
     * Release a voucher reservation.
     */
    public function release(string $code): void;

    /**
     * Redeem a voucher after successful order.
     */
    public function redeem(string $code, string $orderId): void;
}
