<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Events\VoucherCreated;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\Support\VoucherAffiliateOwnershipGuard;
use Akaunting\Money\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Create a new voucher.
 */
final class CreateVoucher
{
    use AsAction;
    use NormalizesVoucherCodes;

    /**
     * Create a new voucher with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): VoucherModel
    {
        $this->validateData($data);

        if (isset($data['currency']) && is_string($data['currency']) && $data['currency'] !== '') {
            $data['currency'] = mb_strtoupper($data['currency']);
        }

        $codeProvided = isset($data['code']);
        $attempts = $codeProvided ? 1 : 3;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return DB::transaction(function () use ($data): VoucherModel {
                    $code = $data['code'] ?? $this->generateCode();
                    $normalizedCode = $this->normalizeCode($code);

                    $createData = [
                        'code' => $normalizedCode,
                        'name' => $data['name'] ?? $normalizedCode,
                        'type' => $data['type'],
                        'value' => $data['value'],
                        'value_config' => $data['value_config'] ?? $data['valueConfig'] ?? null,
                        'credit_destination' => $data['credit_destination'] ?? $data['creditDestination'] ?? null,
                        'credit_delay_hours' => $data['credit_delay_hours'] ?? $data['creditDelayHours'] ?? 0,
                        'currency' => $data['currency'] ?? config('vouchers.default_currency', 'MYR'),
                        'description' => $data['description'] ?? null,
                        'status' => $data['status'] ?? Active::class,
                        'usage_limit' => $data['max_uses'] ?? $data['usage_limit'] ?? null,
                        'usage_limit_per_user' => $data['max_uses_per_user'] ?? $data['usage_limit_per_user'] ?? null,
                        'min_cart_value' => $data['min_order_value'] ?? $data['min_cart_value'] ?? null,
                        'max_discount' => $data['max_discount_value'] ?? $data['max_discount'] ?? null,
                        'starts_at' => $data['starts_at'] ?? null,
                        'expires_at' => $data['expires_at'] ?? null,
                        'metadata' => $data['metadata'] ?? null,
                        'target_definition' => $data['target_definition'] ?? null,
                        'stacking_rules' => $data['stacking_rules'] ?? null,
                        'exclusion_groups' => $data['exclusion_groups'] ?? null,
                        'stacking_priority' => $data['stacking_priority'] ?? 100,
                        'allows_manual_redemption' => $data['allows_manual_redemption'] ?? false,
                        'promotion_id' => $data['promotion_id'] ?? $data['promotionId'] ?? null,
                        'affiliate_id' => $data['affiliate_id'] ?? $data['affiliateId'] ?? null,
                        'affiliate_program_id' => $data['affiliate_program_id'] ?? $data['affiliateProgramId'] ?? null,
                        'affiliate_commission_type' => $data['affiliate_commission_type'] ?? $data['affiliateCommissionType'] ?? null,
                        'affiliate_commission_value' => $data['affiliate_commission_value'] ?? $data['affiliateCommissionValue'] ?? null,
                        'affiliate_upline_levels' => $data['affiliate_upline_levels'] ?? $data['affiliateUplineLevels'] ?? null,
                    ];

                    $createData = VoucherAffiliateOwnershipGuard::sanitize($createData);

                    // Handle owner assignment
                    if (
                        config('vouchers.owner.enabled', false)
                        && config('vouchers.owner.auto_assign_on_create', true)
                    ) {
                        $owner = OwnerContext::resolve();

                        if ($owner !== null) {
                            // Defense-in-depth: never trust inbound owner columns when a
                            // concrete owner context is resolved for this request.
                            $createData['owner_type'] = $owner->getMorphClass();
                            $createData['owner_id'] = $owner->getKey();
                        } elseif (isset($data['owner_type'], $data['owner_id'])) {
                            // Explicit system-level writes may pass owner columns when no
                            // owner context is currently resolved.
                            $createData['owner_type'] = $data['owner_type'];
                            $createData['owner_id'] = $data['owner_id'];
                        }
                    } elseif (isset($data['owner_type'], $data['owner_id'])) {
                        $createData['owner_type'] = $data['owner_type'];
                        $createData['owner_id'] = $data['owner_id'];
                    }

                    $voucher = VoucherModel::create($createData);

                    event(new VoucherCreated(VoucherData::fromModel($voucher)));

                    return $voucher;
                });
            } catch (QueryException $exception) {
                if ($codeProvided || ! $this->isUniqueConstraintViolation($exception) || $attempt === $attempts) {
                    throw $exception;
                }
            }
        }

        throw new LogicException('Unable to generate a unique voucher code.');
    }

    private function generateCode(): string
    {
        /** @var string $prefix */
        $prefix = (string) config('vouchers.code.prefix', '');
        $length = (int) config('vouchers.code.length', 8);

        return $this->normalizeCode($prefix . Str::random($length));
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function validateData(array $data): void
    {
        /** @var array<string, string> $errors */
        $errors = [];

        $type = $data['type'] ?? null;

        if ($type instanceof VoucherType) {
            $voucherType = $type;
        } elseif (is_string($type) && VoucherType::tryFrom($type) !== null) {
            $voucherType = VoucherType::from($type);
        } else {
            $voucherType = null;
            $errors['type'] = 'A valid voucher type is required.';
        }

        $value = $this->coercedInt($data['value'] ?? null);

        if ($value === null) {
            $errors['value'] = 'A voucher value is required.';
        } elseif ($value < 0) {
            $errors['value'] = 'The voucher value cannot be negative.';
        } elseif ($voucherType === VoucherType::Percentage && $value > 10000) {
            $errors['value'] = 'Percentage values are basis points and cannot exceed 10000.';
        }

        if (isset($data['currency']) && $data['currency'] !== '') {
            $currency = is_string($data['currency']) ? mb_strtoupper($data['currency']) : '';

            if (! preg_match('/^[A-Z]{3}$/', $currency) || ! array_key_exists($currency, Currency::getCurrencies())) {
                $errors['currency'] = 'The currency must be a supported 3-letter code.';
            }
        }

        foreach (['usage_limit', 'max_uses', 'usage_limit_per_user', 'max_uses_per_user'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }

            $limit = $this->coercedInt($data[$key]);

            if ($limit === null || $limit < 0) {
                $errors[$key] = 'Usage limits must be zero or a positive integer.';
            }
        }

        foreach (['min_order_value', 'min_cart_value', 'max_discount_value', 'max_discount', 'credit_delay_hours', 'creditDelayHours'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }

            $amount = $this->coercedInt($data[$key]);

            if ($amount === null || $amount < 0) {
                $errors[$key] = 'Amount and delay values must be zero or a positive integer.';
            }
        }

        $startsAt = $this->parsedDate($data['starts_at'] ?? null);
        $expiresAt = $this->parsedDate($data['expires_at'] ?? null);

        if (array_key_exists('starts_at', $data) && $data['starts_at'] !== null && $startsAt === null) {
            $errors['starts_at'] = 'The start date is not a valid date.';
        }

        if (array_key_exists('expires_at', $data) && $data['expires_at'] !== null && $expiresAt === null) {
            $errors['expires_at'] = 'The expiry date is not a valid date.';
        }

        if ($startsAt !== null && $expiresAt !== null && $startsAt->greaterThanOrEqualTo($expiresAt)) {
            $errors['expires_at'] = 'The expiry date must be after the start date.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function coercedInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', mb_trim($value))) {
            return (int) $value;
        }

        if (is_float($value) && (float) (int) $value === $value) {
            return (int) $value;
        }

        return null;
    }

    private function parsedDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
