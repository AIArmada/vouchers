<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\Stacking\Contracts\StackingPolicyInterface;
use AIArmada\Vouchers\Support\VoucherRulesFactory;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class ApplyVoucherToCart
{
    use AsAction;
    use QueriesVouchers;

    public function __construct(
        private readonly VoucherService $voucherService,
    ) {}

    public function handle(
        Cart $cart,
        string $code,
        ?StackingPolicyInterface $policy = null,
    ): VoucherCondition {
        $code = $this->normalizeCode($code);

        $validationResult = ValidateVoucherCode::run($code, $cart);
        if (! $validationResult->isValid) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' cannot be applied: {$validationResult->reason}"
            );
        }

        if ($this->isAlreadyApplied($cart, $code)) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' is already applied to this cart"
            );
        }

        $voucherData = $this->voucherService->find($code);
        if ($voucherData === null) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' not found",
            );
        }

        $voucherCondition = new VoucherCondition($voucherData);
        $existingVouchers = collect($this->getAppliedVoucherConditions($cart));

        $policy ??= app(StackingPolicyInterface::class);
        $decision = $policy->canAdd(
            $voucherCondition,
            $existingVouchers,
            $cart,
        );

        if ($decision->isDenied()) {
            $replaceWhenMaxReached = (bool) config('vouchers.cart.replace_when_max_reached', true);

            if ($policy->isAutoReplaceEnabled() && $replaceWhenMaxReached && $decision->hasConflict()) {
                $conflicting = $decision->conflictsWith;
                if ($conflicting !== null) {
                    RemoveVoucherFromCart::run($cart, $conflicting->getVoucherCode());
                }
            } else {
                throw new InvalidVoucherException('Cart already has the maximum number of vouchers');
            }
        }

        $this->ensureVoucherRulesFactory($cart);

        try {
            $cart->registerDynamicCondition(
                $voucherCondition->toCartCondition(),
                null,
                $voucherCondition->getRuleFactoryKey(),
                $voucherCondition->getRuleFactoryContext()
            );
        } catch (Throwable $exception) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' cannot be applied: {$exception->getMessage()}",
                previous: $exception
            );
        }

        VoucherApplied::dispatch($cart, $voucherData);

        return $voucherCondition;
    }

    private function isAlreadyApplied(Cart $cart, string $code): bool
    {
        $normalized = $this->normalizeCode($code);

        foreach ($this->collectVoucherConditions($cart) as $condition) {
            if ($this->normalizeCode($condition->getVoucherCode()) === $normalized) {
                return true;
            }
        }

        return false;
    }

    private function getAppliedVoucherConditions(Cart $cart): array
    {
        return array_values($this->collectVoucherConditions($cart));
    }

    private function collectVoucherConditions(Cart $cart): array
    {
        $collections = [
            $cart->getDynamicConditions(),
            $cart->getConditions(),
        ];

        $conditions = [];

        foreach ($collections as $collection) {
            foreach ($collection as $condition) {
                if ($condition instanceof VoucherCondition) {
                    $voucherCondition = $condition;
                } elseif ($condition instanceof CartCondition && $condition->getType() === 'voucher') {
                    $voucherCondition = VoucherCondition::fromCartCondition($condition);

                    if ($voucherCondition === null) {
                        continue;
                    }
                } else {
                    continue;
                }

                $conditions[$this->normalizeCode($voucherCondition->getVoucherCode())] = $voucherCondition;
            }
        }

        return $conditions;
    }

    private function ensureVoucherRulesFactory(Cart $cart): void
    {
        $factory = $cart->getRulesFactory();

        if ($factory instanceof VoucherRulesFactory) {
            return;
        }

        if ($factory === null) {
            $cart->withRulesFactory(app(VoucherRulesFactory::class));

            return;
        }

        $cart->withRulesFactory(new VoucherRulesFactory($factory));
    }
}
