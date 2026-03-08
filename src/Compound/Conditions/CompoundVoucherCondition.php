<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Compound\Conditions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Target;
use AIArmada\Cart\Contracts\CartConditionConvertible;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Compound\Matchers\AbstractProductMatcher;
use AIArmada\Vouchers\Contracts\ProductMatcherInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Facades\Voucher;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Abstract base class for compound voucher conditions (BOGO, Tiered, Bundle, Cashback).
 *
 * @implements Arrayable<string, mixed>
 */
abstract class CompoundVoucherCondition implements Arrayable, CartConditionConvertible
{
    public const RULE_FACTORY_KEY = 'compound_voucher';

    protected VoucherData $voucher;

    /** @var array<string, mixed> */
    protected array $valueConfig;

    protected int $order;

    protected bool $dynamic;

    protected ?CartCondition $cartCondition = null;

    /**
     * @param  VoucherData  $voucher  The voucher data
     * @param  array<string, mixed>  $valueConfig  The compound voucher configuration
     * @param  int  $order  Application order
     * @param  bool  $dynamic  Whether to validate dynamically
     */
    public function __construct(
        VoucherData $voucher,
        array $valueConfig,
        int $order = 0,
        bool $dynamic = true
    ) {
        $this->voucher = $voucher;
        $this->valueConfig = $valueConfig;
        $this->order = $order;
        $this->dynamic = $dynamic;
    }

    /**
     * Calculate the discount amount for this compound voucher.
     *
     * @return int Discount amount in cents (positive value)
     */
    abstract public function calculateDiscount(Cart $cart): int;

    /**
     * Get a human-readable description of the discount.
     */
    abstract public function getDiscountDescription(Cart $cart): string;

    /**
     * Check if the cart meets the requirements for this voucher.
     */
    abstract public function meetsRequirements(Cart $cart): bool;

    /**
     * Create the appropriate compound condition based on voucher type.
     */
    final public static function create(VoucherData $voucher, int $order = 0, bool $dynamic = true): ?self
    {
        $valueConfig = $voucher->valueConfig ?? [];

        return match ($voucher->type) {
            VoucherType::BuyXGetY => new BOGOVoucherCondition($voucher, $valueConfig, $order, $dynamic),
            VoucherType::Tiered => new TieredVoucherCondition($voucher, $valueConfig, $order, $dynamic),
            VoucherType::Bundle => new BundleVoucherCondition($voucher, $valueConfig, $order, $dynamic),
            VoucherType::Cashback => new CashbackVoucherCondition($voucher, $valueConfig, $order, $dynamic),
            default => null,
        };
    }

    final public function toCartCondition(): CartCondition
    {
        if ($this->cartCondition instanceof CartCondition) {
            return $this->cartCondition;
        }

        $this->cartCondition = new CartCondition(
            name: "voucher_{$this->voucher->code}",
            type: 'voucher',
            target: $this->getConditionTarget(),
            value: $this->getConditionValue(),
            attributes: $this->getConditionAttributes(),
            order: $this->order,
            rules: $this->dynamic ? [[$this, 'validateVoucher']] : null
        );

        return $this->cartCondition;
    }

    /**
     * Validate that the voucher can still be applied.
     */
    final public function validateVoucher(Cart $cart, ?CartItem $item = null): bool
    {
        $validationResult = Voucher::validate($this->voucher->code, $cart);

        return $validationResult->isValid;
    }

    /**
     * Get the voucher data.
     */
    final public function getVoucher(): VoucherData
    {
        return $this->voucher;
    }

    /**
     * Get the voucher code.
     */
    final public function getVoucherCode(): string
    {
        return $this->voucher->code;
    }

    /**
     * Get the value configuration.
     *
     * @return array<string, mixed>
     */
    final public function getValueConfig(): array
    {
        return $this->valueConfig;
    }

    final public function getRuleFactoryKey(): string
    {
        return self::RULE_FACTORY_KEY;
    }

    /**
     * @return array<string, mixed>
     */
    final public function getRuleFactoryContext(): array
    {
        return [
            'voucher_code' => $this->voucher->code,
            'voucher_id' => $this->voucher->id,
            'voucher_type' => $this->voucher->type->value,
        ];
    }

    final public function getName(): string
    {
        return "voucher_{$this->voucher->code}";
    }

    final public function getType(): string
    {
        return 'voucher';
    }

    final public function getOrder(): int
    {
        return $this->order;
    }

    final public function isDynamic(): bool
    {
        return $this->dynamic;
    }

    /**
     * @return array<string, mixed>
     */
    final public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'type' => $this->getType(),
            'voucher' => [
                'id' => $this->voucher->id,
                'code' => $this->voucher->code,
                'type' => $this->voucher->type->value,
            ],
            'value_config' => $this->valueConfig,
            'order' => $this->order,
            'is_dynamic' => $this->dynamic,
            'is_compound' => true,
        ];
    }

    /**
     * Get a config value with optional default.
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->valueConfig;

        foreach ($keys as $k) {
            if (! is_array($value) || ! array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Create a product matcher from config.
     */
    protected function createMatcher(array $config): ProductMatcherInterface
    {
        return AbstractProductMatcher::create($config);
    }

    /**
     * Get all cart items as a collection.
     *
     * @return Collection<int, CartItem>
     */
    protected function getCartItems(Cart $cart): Collection
    {
        return collect($cart->getItems());
    }

    /**
     * Get the condition target for the cart condition.
     */
    protected function getConditionTarget(): ConditionTarget
    {
        return Target::cart()
            ->phase($this->getConditionPhase())
            ->applyAggregate()
            ->withMeta($this->getTargetMeta())
            ->build();
    }

    /**
     * Get the condition phase for this voucher type.
     */
    protected function getConditionPhase(): ConditionPhase
    {
        return ConditionPhase::CART_SUBTOTAL;
    }

    /**
     * Get the condition value string.
     */
    protected function getConditionValue(): string
    {
        return '+0'; // Will be calculated dynamically
    }

    /**
     * Get target metadata.
     *
     * @return array<string, mixed>
     */
    protected function getTargetMeta(): array
    {
        return [
            'source' => 'compound_voucher',
            'voucher_id' => $this->voucher->id,
            'voucher_code' => $this->voucher->code,
            'voucher_type' => $this->voucher->type->value,
        ];
    }

    /**
     * Get condition attributes.
     *
     * @return array<string, mixed>
     */
    protected function getConditionAttributes(): array
    {
        return [
            'voucher_id' => $this->voucher->id,
            'voucher_code' => $this->voucher->code,
            'voucher_type' => $this->voucher->type->value,
            'description' => $this->voucher->description,
            'value_config' => $this->valueConfig,
            'voucher_data' => $this->voucher->toArray(),
            'is_compound' => true,
        ];
    }
}
