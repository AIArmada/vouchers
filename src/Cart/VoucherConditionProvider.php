<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Contracts\ConditionProviderInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Services\VoucherService;

/**
 * Provides voucher-based cart conditions.
 *
 * This class bridges the Voucher package with the Cart package,
 * converting applied vouchers into CartCondition objects.
 */
final readonly class VoucherConditionProvider implements ConditionProviderInterface
{
    private const string VOUCHER_METADATA_KEY = 'voucher_codes';

    private const string CONDITION_TYPE = 'voucher';

    private const int PRIORITY = 100;

    public function __construct(
        private VoucherService $voucherService
    ) {}

    /**
     * Get voucher conditions applicable to the cart.
     *
     * Reads voucher codes from cart metadata and creates conditions
     * for each valid voucher.
     *
     * @return array<CartCondition>
     */
    public function getConditionsFor(Cart $cart): array
    {
        $conditions = [];

        /** @var array<string> $voucherCodes */
        $voucherCodes = $cart->getMetadata(self::VOUCHER_METADATA_KEY, []);

        if (empty($voucherCodes)) {
            return [];
        }

        foreach ($voucherCodes as $code) {
            $voucher = $this->voucherService->find($code);

            if ($voucher === null) {
                continue;
            }

            $validationResult = $this->voucherService->validate($code, $cart);

            if (! $validationResult->isValid) {
                continue;
            }

            $condition = $this->createConditionFromVoucher($voucher);

            if ($condition !== null) {
                $conditions[] = $condition;
            }
        }

        return $conditions;
    }

    /**
     * Validate that a condition is still applicable.
     *
     * Called during checkout to ensure vouchers are still valid.
     */
    public function validate(CartCondition $condition, Cart $cart): bool
    {
        if ($condition->getType() !== self::CONDITION_TYPE) {
            return true;
        }

        $code = $condition->getName();
        $validationResult = $this->voucherService->validate($code, $cart);

        return $validationResult->isValid;
    }

    /**
     * Get the condition type identifier.
     */
    public function getType(): string
    {
        return self::CONDITION_TYPE;
    }

    /**
     * Get the priority for condition application.
     * Vouchers are applied after shipping (50-99) but before tax (150-199).
     */
    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * Create a CartCondition from voucher data.
     */
    private function createConditionFromVoucher(VoucherData $voucher): ?CartCondition
    {
        $value = $this->calculateConditionValue($voucher);

        if ($value === null) {
            return null;
        }

        return new CartCondition(
            name: $voucher->code,
            type: self::CONDITION_TYPE,
            target: $this->buildTargetDefinition($voucher),
            value: $value,
            attributes: $this->buildAttributes($voucher),
            order: self::PRIORITY
        );
    }

    /**
     * Calculate the condition value based on voucher type.
     *
     * Note: Percentage values are stored as basis points (e.g., 1250 = 12.5%).
     * Fixed values are stored as cents (e.g., 1000 = $10.00).
     */
    private function calculateConditionValue(VoucherData $voucher): ?string
    {
        return match ($voucher->type) {
            VoucherType::Fixed => '-' . (string) $voucher->value,
            VoucherType::Percentage => $this->formatPercentageValue($voucher->value),
            VoucherType::FreeShipping => '-100%',
            default => null,
        };
    }

    /**
     * Format a basis points value as a percentage string.
     *
     * @param  int  $basisPoints  Value in basis points (e.g., 1250 = 12.5%)
     */
    private function formatPercentageValue(int $basisPoints): string
    {
        $percentage = $basisPoints / 100;

        if ($percentage === (int) $percentage) {
            return '-' . (int) $percentage . '%';
        }

        return '-' . mb_rtrim(mb_rtrim(number_format($percentage, 2, '.', ''), '0'), '.') . '%';
    }

    /**
     * Build the target definition for the condition.
     *
     * @return array<string, mixed>
     */
    private function buildTargetDefinition(VoucherData $voucher): array
    {
        $phase = match ($voucher->type) {
            VoucherType::FreeShipping => ConditionPhase::SHIPPING,
            default => ConditionPhase::CART_SUBTOTAL,
        };

        return [
            'scope' => ConditionScope::CART->value,
            'phase' => $phase->value,
            'application' => ConditionApplication::AGGREGATE->value,
        ];
    }

    /**
     * Build condition attributes from voucher data.
     *
     * @return array<string, mixed>
     */
    private function buildAttributes(VoucherData $voucher): array
    {
        return [
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'voucher_type' => $voucher->type->value,
            'description' => $voucher->description,
            'min_cart_value' => $voucher->minCartValue,
            'max_discount' => $voucher->maxDiscount,
            'currency' => $voucher->currency,
        ];
    }
}
