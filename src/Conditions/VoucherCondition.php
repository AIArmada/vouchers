<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Conditions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Target;
use AIArmada\Cart\Contracts\CartConditionConvertible;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Facades\Voucher;
use Illuminate\Contracts\Support\Arrayable;
use JsonException;

/**
 * VoucherCondition bridges the voucher system with cart's condition system.
 *
 * This class implements the same interfaces as CartCondition to provide voucher-specific behavior
 * while maintaining compatibility with the cart's pricing engine.
 *
 * @implements \Illuminate\Contracts\Support\Arrayable<string, mixed>
 */
class VoucherCondition implements Arrayable, CartConditionConvertible
{
    public const RULE_FACTORY_KEY = 'voucher';

    private string $name;

    private string $type;

    private ConditionTarget $target;

    private string $value;

    /** @var array<string, mixed> */
    private array $attributes;

    private int $order;

    /** @var ?array<callable> */
    private ?array $rules;

    private VoucherData $voucher;

    private ?CartCondition $cartCondition = null;

    /**
     * Create a new voucher condition.
     *
     * @param  VoucherData  $voucher  The voucher data to apply
     * @param  int  $order  The order in which this condition should be applied
     * @param  bool  $dynamic  Whether to add validation rules (true for dynamic, false for static)
     */
    public function __construct(VoucherData $voucher, int $order = 0, bool $dynamic = true, ?ConditionTarget $targetDefinition = null)
    {
        $this->voucher = $voucher;

        // Convert voucher to cart condition format
        $value = $this->formatVoucherValue($voucher);
        $target = $targetDefinition ?? $this->determineTarget($voucher);

        $this->name = "voucher_{$voucher->code}";
        $this->type = 'voucher';
        $this->target = $target;
        $this->value = $value;
        $this->attributes = [
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'voucher_type' => $voucher->type->value,
            'description' => $voucher->description,
            'original_value' => $voucher->value,
            'voucher_data' => $voucher->toArray(),
        ];
        $this->order = $order;
        $this->rules = $dynamic ? [[$this, 'validateVoucher']] : null;
    }

    public static function fromCartCondition(CartCondition $condition): ?self
    {
        if ($condition->getType() !== 'voucher') {
            return null;
        }

        $attributes = $condition->getAttributes();
        $voucherData = $attributes['voucher_data'] ?? null;

        if (! is_array($voucherData)) {
            return null;
        }

        $data = VoucherData::fromArray($voucherData);

        $instance = new self(
            voucher: $data,
            order: $condition->getOrder(),
            dynamic: $condition->isDynamic(),
            targetDefinition: $condition->getTargetDefinition()
        );

        $instance->cartCondition = $condition;

        return $instance;
    }

    public function toCartCondition(): CartCondition
    {
        if ($this->cartCondition instanceof CartCondition) {
            return $this->cartCondition;
        }

        $this->cartCondition = new CartCondition(
            name: $this->name,
            type: $this->type,
            target: $this->target,
            value: $this->value,
            attributes: $this->attributes,
            order: $this->order,
            rules: $this->isDynamic() ? $this->rules : null
        );

        return $this->cartCondition;
    }

    /**
     * Validate that the voucher can still be applied to the cart.
     *
     * This is called by the cart's condition system before applying the discount.
     *
     * @param  Cart  $cart  The cart to validate against
     * @param  CartItem|null  $item  The item being evaluated (null for cart-level conditions)
     * @return bool True if voucher is valid, false otherwise
     */
    public function validateVoucher(Cart $cart, ?CartItem $item = null): bool
    {
        // Re-validate the voucher against current cart state
        $validationResult = Voucher::validate($this->voucher->code, $cart);

        return $validationResult->isValid;
    }

    /**
     * Get the voucher data.
     */
    public function getVoucher(): VoucherData
    {
        return $this->voucher;
    }

    /**
     * Get the voucher code.
     */
    public function getVoucherCode(): string
    {
        return $this->voucher->code;
    }

    /**
     * Get the voucher ID.
     */
    public function getVoucherId(): string
    {
        return $this->voucher->id;
    }

    public function getRuleFactoryKey(): string
    {
        return self::RULE_FACTORY_KEY;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRuleFactoryContext(): array
    {
        return [
            'voucher_code' => $this->voucher->code,
            'voucher_id' => $this->voucher->id,
        ];
    }

    /**
     * Check if this is a free shipping voucher.
     */
    public function isFreeShipping(): bool
    {
        return $this->voucher->type === VoucherType::FreeShipping;
    }

    /**
     * Apply the condition to a value.
     *
     * For free shipping vouchers, this should remove shipping costs.
     * For other vouchers, use the parent's apply logic.
     *
     * @param  float  $value  The base value to apply the condition to
     * @return float The modified value
     */
    public function apply(float $value): float
    {
        if ($this->isFreeShipping()) {
            // Free shipping logic can be handled here or by the shipping calculator
            // For now, we return the value as-is and let shipping conditions handle it
            return $value;
        }

        // Apply max discount cap if set
        $result = $this->applyCondition($value);

        if ($this->voucher->maxDiscount !== null) {
            $discount = $value - $result;
            $maxDiscount = $this->voucher->maxDiscount;

            if ($discount > $maxDiscount) {
                $result = $value - $maxDiscount;
            }
        }

        return $result;
    }

    /**
     * Get calculated value for display
     */
    public function getCalculatedValue(float $baseValue): float
    {
        return $this->apply($baseValue) - $baseValue;
    }

    /**
     * Check if condition is a discount
     */
    public function isDiscount(): bool
    {
        $operator = $this->getOperator();
        $value = $this->parseValue();

        return ($operator === '-') || ($operator === '%' && $value < 0);
    }

    /**
     * Check if condition is a charge/fee
     */
    public function isCharge(): bool
    {
        $operator = $this->getOperator();
        $value = $this->parseValue();

        return ($operator === '+') || ($operator === '%' && $value > 0);
    }

    /**
     * Check if condition is percentage-based
     */
    public function isPercentage(): bool
    {
        return $this->getOperator() === '%';
    }

    /**
     * Check if this is a dynamic condition
     */
    public function isDynamic(): bool
    {
        return $this->rules !== null && ! empty($this->rules);
    }

    /**
     * Get the validation rules for this condition
     *
     * @return ?array<callable(): mixed>
     */
    public function getRules(): ?array
    {
        return $this->rules;
    }

    /**
     * Get the condition name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the condition type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the condition target definition.
     */
    public function getTargetDefinition(): ConditionTarget
    {
        return $this->target;
    }

    /**
     * Get the condition value.
     */
    public function getValue(): string|float
    {
        return $this->value;
    }

    /**
     * Get the condition attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get a specific attribute.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Get the condition order.
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * Convert to array with voucher-specific data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'type' => $this->type,
            'target_definition' => $this->target->toArray(),
            'value' => $this->value,
            'attributes' => $this->attributes,
            'order' => $this->order,
            'rules' => $this->rules,
            'operator' => $this->getOperator(),
            'parsed_value' => $this->parseValue(),
            'is_discount' => $this->isDiscount(),
            'is_charge' => $this->isCharge(),
            'is_percentage' => $this->isPercentage(),
            'is_dynamic' => $this->isDynamic(),
        ];

        $array['voucher'] = [
            'id' => $this->voucher->id,
            'code' => $this->voucher->code,
            'type' => $this->voucher->type->value,
            'value' => $this->voucher->value,
            'description' => $this->voucher->description,
            'max_discount_amount' => $this->voucher->maxDiscount,
            'is_free_shipping' => $this->isFreeShipping(),
        ];

        return $array;
    }

    /**
     * Convert to JSON
     */
    public function toJson(int $options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if ($json === false) {
            throw new JsonException('Failed to encode condition to JSON');
        }

        return $json;
    }

    /**
     * Serialize for JSON
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Apply the condition logic (similar to CartCondition::apply)
     */
    private function applyCondition(float $value): float
    {
        $conditionValue = $this->parseValue();

        $result = match ($this->getOperator()) {
            '+' => $value + $conditionValue,
            '-' => $value - $conditionValue,
            '*' => $value * abs($conditionValue),
            '/' => abs($conditionValue) > 0 ? $value / abs($conditionValue) : $value,
            '%' => $this->applyPercentage($value, $conditionValue),
            default => $value,
        };

        return max(0, $result); // Ensure result is not negative
    }

    /**
     * Apply percentage calculation
     */
    private function applyPercentage(float $value, float $percentage): float
    {
        return $value + ($value * $percentage);
    }

    /**
     * Get the operator from value
     */
    private function getOperator(): string
    {
        $value = (string) $this->value;

        if (str_ends_with($value, '%')) {
            return '%';
        }

        return match ($value[0] ?? '') {
            '+' => '+',
            '-' => '-',
            '*' => '*',
            '/' => '/',
            default => '+', // Default to addition if no operator
        };
    }

    /**
     * Parse the numeric value from the condition value
     */
    private function parseValue(): float
    {
        $value = (string) $this->value;

        // Handle percentage
        if (str_ends_with($value, '%')) {
            return $this->parsePercentValue($value);
        }

        // Handle operators
        if (in_array($value[0] ?? '', ['+', '-', '*', '/'])) {
            $numericValue = (float) mb_substr($value, 1);
        } else {
            $numericValue = (float) $value;
        }

        return $numericValue;
    }

    /**
     * Parse a percentage value string (e.g., '10%')
     */
    private function parsePercentValue(string $value): float
    {
        $numericValue = (float) mb_substr($value, 0, -1);

        return $numericValue / 100;
    }

    /**
     * Format the voucher value for cart condition system.
     *
     * @return string The formatted value (e.g., '-10%', '-2000', '+0')
     */
    private function formatVoucherValue(VoucherData $voucher): string
    {
        return match ($voucher->type) {
            VoucherType::Percentage => '-'.($voucher->value / 100).'%', // Convert basis points to percentage (1000 = 10.00%)
            VoucherType::Fixed => "-{$voucher->value}", // Value is already in cents
            VoucherType::FreeShipping => '+0', // Free shipping is handled separately
        };
    }

    /**
     * Determine the condition target definition based on voucher metadata and type.
     */
    private function determineTarget(VoucherData $voucher): ConditionTarget
    {
        $explicitTarget = $voucher->targetDefinition;
        if (is_array($explicitTarget)) {
            return ConditionTarget::from($explicitTarget)
                ->with($this->targetMeta($voucher));
        }

        if ($voucher->type === VoucherType::FreeShipping) {
            return Target::cart()
                ->phase(ConditionPhase::GRAND_TOTAL)
                ->applyAggregate()
                ->withMeta($this->targetMeta($voucher))
                ->build();
        }

        return Target::cart()
            ->phase(ConditionPhase::CART_SUBTOTAL)
            ->applyAggregate()
            ->withMeta($this->targetMeta($voucher))
            ->build();
    }

    /**
     * @return array<string, mixed>
     */
    private function targetMeta(VoucherData $voucher): array
    {
        return [
            'source' => 'voucher',
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'voucher_type' => $voucher->type->value,
        ];
    }
}
