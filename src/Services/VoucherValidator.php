<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Services;

use AIArmada\Vouchers\Contracts\VoucherOwnerResolver;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class VoucherValidator
{
    public function __construct(
        protected VoucherOwnerResolver $ownerResolver
    ) {}

    public function validate(string $code, mixed $cart): VoucherValidationResult
    {
        Log::info('🔍 VoucherValidator::validate() called', ['code' => $code]);

        $code = $this->normalizeCode($code);

        // Find voucher
        $voucher = $this->query()
            ->where('code', $code)
            ->first();

        if (! $voucher) {
            return VoucherValidationResult::invalid('Voucher not found.');
        }

        // Debug logging
        Log::debug('Voucher validation check', [
            'code' => $code,
            'voucher_id' => $voucher->id,
            'status' => $voucher->status,
            'hasStarted' => $voucher->hasStarted(),
            'isExpired' => $voucher->isExpired(),
            'isActive' => $voucher->isActive(),
            'starts_at' => $voucher->starts_at,
            'expires_at' => $voucher->expires_at,
            'available_from' => $voucher->available_from,
            'available_until' => $voucher->available_until,
        ]);

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
            if ($voucher->status === VoucherStatus::Paused) {
                return VoucherValidationResult::invalid('Voucher is paused.');
            }

            if ($voucher->status === VoucherStatus::Depleted) {
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
                return VoucherValidationResult::invalid(
                    "Minimum cart value of {$voucher->currency} {$voucher->min_cart_value} required.",
                    ['min_cart_value' => $voucher->min_cart_value, 'current_cart_value' => $cartTotal]
                );
            }
        }

        return VoucherValidationResult::valid();
    }

    /**
     * @return Builder<Voucher>
     */
    protected function query(): Builder
    {
        return Voucher::query()->forOwner(
            $this->resolveOwner(),
            (bool) config('vouchers.owner.include_global', true)
        );
    }

    protected function resolveOwner(): ?Model
    {
        if (! config('vouchers.owner.enabled', false)) {
            return null;
        }

        return $this->ownerResolver->resolve();
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

    protected function getCartTotal(mixed $cart): float
    {
        // Handle different cart types
        if (is_object($cart) && method_exists($cart, 'getRawSubtotalWithoutConditions')) {
            return $cart->getRawSubtotalWithoutConditions();
        }

        if (is_array($cart) && isset($cart['total'])) {
            return (float) $cart['total'];
        }

        return 0.0;
    }

    protected function normalizeCode(string $code): string
    {
        if (config('vouchers.code.auto_uppercase', true)) {
            return mb_strtoupper(mb_trim($code));
        }

        return mb_trim($code);
    }
}
