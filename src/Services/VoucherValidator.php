<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingMode;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Expired;
use AIArmada\Vouchers\States\Paused;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Throwable;

class VoucherValidator
{
    use QueriesVouchers;

    public function __construct(
        private readonly TargetingEngineInterface $targetingEngine
    ) {}

    public function validate(string $code, mixed $cart): VoucherValidationResult
    {
        $code = $this->normalizeCode($code);

        // Find voucher
        $voucher = $this->voucherQuery()
            ->where('code', $code)
            ->first();

        if (! $voucher) {
            return VoucherValidationResult::invalid('Voucher not found.');
        }

        // Check start date (before status check, as time-based validations are more specific)
        if (! $voucher->hasStarted()) {
            return VoucherValidationResult::invalid(
                'Voucher is not yet available.',
                ['starts_at' => $voucher->starts_at]
            );
        }

        // Check expiry (before status check, as time-based validations are more specific)
        if ($voucher->isExpired()) {
            return VoucherValidationResult::invalid(
                'Voucher has expired.',
                ['expires_at' => $voucher->expires_at]
            );
        }

        // Check status (after time-based checks)
        if (! $voucher->isActive()) {
            if ($voucher->status instanceof Paused) {
                return VoucherValidationResult::invalid('Voucher is paused.');
            }

            if ($voucher->status instanceof Expired) {
                return VoucherValidationResult::invalid('Voucher has expired.');
            }

            if ($voucher->status instanceof Depleted) {
                return VoucherValidationResult::invalid('Voucher usage limit has been reached.');
            }

            return VoucherValidationResult::invalid('Voucher is not active.');
        }

        // Check global usage limit
        if (config('vouchers.validation.check_global_limit', true)) {
            if (! $voucher->hasUsageLimitRemaining()) {
                return VoucherValidationResult::invalid('Voucher usage limit has been reached.');
            }
        }

        // Check per-user usage limit
        if (config('vouchers.validation.check_user_limit', true) && $voucher->usage_limit_per_user) {
            $user = $this->getUser();
            if ($user) {
                $usageCount = VoucherUsage::where('voucher_id', $voucher->id)
                    ->where('redeemed_by_type', $user->getMorphClass())
                    ->where('redeemed_by_id', $user->getKey())
                    ->count();

                if ($usageCount >= $voucher->usage_limit_per_user) {
                    return VoucherValidationResult::invalid(
                        'You have already used this voucher the maximum number of times.'
                    );
                }
            }
        }

        // Check minimum cart value
        if (config('vouchers.validation.check_min_cart_value', true) && $voucher->min_cart_value) {
            $cartTotal = $this->getCartTotal($cart);

            if ($cartTotal < $voucher->min_cart_value) {
                $formattedMinValue = MoneyFormatter::formatMinor($voucher->min_cart_value, (string) ($voucher->currency ?? config('vouchers.default_currency', 'MYR')));

                Log::info('Voucher min cart value check failed', [
                    'code' => $code,
                    'cartTotal' => $cartTotal,
                    'min_cart_value' => $voucher->min_cart_value,
                ]);

                return VoucherValidationResult::invalid(
                    "Minimum cart value of {$formattedMinValue} required.",
                    ['min_cart_value' => $voucher->min_cart_value, 'current_cart_value' => $cartTotal]
                );
            }
        }

        // Check targeting rules
        if (config('vouchers.validation.check_targeting', true)) {
            $targetingResult = $this->validateTargeting($voucher, $cart);
            if (! $targetingResult->isValid) {
                return $targetingResult;
            }
        }

        return VoucherValidationResult::valid();
    }

    protected function getUser(): ?Model
    {
        $user = Auth::user();

        return $user instanceof Model ? $user : null;
    }

    protected function getUserIdentifier(): string
    {
        $userId = Auth::id();

        if ($userId !== null) {
            return (string) $userId;
        }

        return (string) Session::getId();
    }

    protected function getCartTotal(mixed $cart): int
    {
        // Handle different cart types
        if (is_object($cart) && method_exists($cart, 'getRawSubtotalWithoutConditions')) {
            /** @var int $subtotal */
            $subtotal = $cart->getRawSubtotalWithoutConditions();

            return $subtotal;
        }

        if (is_array($cart) && isset($cart['total'])) {
            /** @var scalar $total */
            $total = $cart['total'];

            return (int) $total;
        }

        return 0;
    }

    /**
     * Validate voucher targeting rules against the cart context.
     */
    protected function validateTargeting(Voucher $voucher, mixed $cart): VoucherValidationResult
    {
        $targetingData = $this->parseTargetingDefinition($voucher->target_definition);

        if ($targetingData === null) {
            return VoucherValidationResult::valid();
        }

        // Fail closed: non-custom mode with an empty rules array means the targeting
        // definition was provided but is invalid — do not grant eligibility.
        if (isset($targetingData['__empty_rules'])) {
            return VoucherValidationResult::invalid(
                'You do not meet the eligibility requirements for this voucher.',
                ['targeting_failed' => true]
            );
        }

        if (! $cart instanceof Cart) {
            return VoucherValidationResult::valid();
        }

        $context = TargetingContext::fromCart($cart);
        $result = $this->targetingEngine->evaluate($targetingData, $context);

        if (! $result) {
            return VoucherValidationResult::invalid(
                'You do not meet the eligibility requirements for this voucher.',
                ['targeting_failed' => true]
            );
        }

        return VoucherValidationResult::valid();
    }

    /**
     * Parse target_definition into targeting engine format.
     *
     * @param  array<string, mixed>|null  $targetDefinition
     * @return array<string, mixed>|null
     */
    protected function parseTargetingDefinition(?array $targetDefinition): ?array
    {
        if ($targetDefinition === null) {
            return null;
        }

        // Stored data may contain a cart-condition target (scope, phase, application)
        // rather than targeting/eligibility rules (mode, rules, expression). When no
        // targeting-specific keys exist, only known cart-condition targets bypass eligibility.
        $hasTargeting = array_key_exists('targeting', $targetDefinition);
        $targeting = $hasTargeting ? $targetDefinition['targeting'] : null;

        if ($this->hasConditionTargetKeys($targetDefinition) && ! $this->isConditionTargetDefinition($targetDefinition)) {
            return $this->invalidTargetingDefinition();
        }

        if ($hasTargeting && $targeting !== null && ! is_array($targeting)) {
            return $this->invalidTargetingDefinition();
        }

        if ($targeting === null) {
            if ($this->isConditionTargetDefinition($targetDefinition)) {
                return null;
            }

            $targeting = $targetDefinition;
        }

        if (! is_array($targeting) || empty($targeting)) {
            return null;
        }

        $modeValue = $targeting['mode'] ?? TargetingMode::All->value;

        if ($modeValue instanceof TargetingMode) {
            $mode = $modeValue;
        } elseif (is_string($modeValue)) {
            $mode = TargetingMode::tryFrom($modeValue);

            if ($mode === null) {
                return $this->invalidTargetingDefinition();
            }
        } else {
            return $this->invalidTargetingDefinition();
        }

        /** @var array<int, array<string, mixed>> $rules */
        $rules = [];
        if (isset($targeting['rules']) && is_array($targeting['rules'])) {
            $rules = array_values($targeting['rules']);
        }

        /** @var array<string, mixed>|null $expression */
        $expression = null;
        if ($mode === TargetingMode::Custom && isset($targeting['expression']) && is_array($targeting['expression'])) {
            $expression = $targeting['expression'];
        }

        if (empty($rules) && $expression === null) {
            // Any mode with no rules and no expression is a degenerate/misconfigured
            // targeting definition. Fail closed (sentinel) for ALL modes — including Custom —
            // rather than silently granting eligibility.
            return $this->invalidTargetingDefinition($mode);
        }

        $data = [
            'mode' => $mode->value,
            'rules' => $rules,
        ];

        if ($expression !== null) {
            $data['expression'] = $expression;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $targetDefinition
     */
    private function isConditionTargetDefinition(array $targetDefinition): bool
    {
        if (! isset($targetDefinition['scope'], $targetDefinition['phase'], $targetDefinition['application'])) {
            return false;
        }

        try {
            ConditionTarget::from($targetDefinition);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function invalidTargetingDefinition(TargetingMode $mode = TargetingMode::All): array
    {
        return ['mode' => $mode->value, 'rules' => [], '__empty_rules' => true];
    }

    /**
     * @param  array<string, mixed>  $targetDefinition
     */
    private function hasConditionTargetKeys(array $targetDefinition): bool
    {
        return array_key_exists('scope', $targetDefinition)
            || array_key_exists('phase', $targetDefinition)
            || array_key_exists('application', $targetDefinition);
    }
}
