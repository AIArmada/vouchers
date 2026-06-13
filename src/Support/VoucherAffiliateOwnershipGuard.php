<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class VoucherAffiliateOwnershipGuard
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitize(array $data): array
    {
        $data = self::sanitizeRelation($data, 'affiliate_id', 'affiliateId', Affiliate::class);
        $data = self::sanitizeRelation($data, 'affiliate_program_id', 'affiliateProgramId', AffiliateProgram::class);

        return $data;
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function sanitizeRelation(array $data, string $snakeKey, string $camelKey, string $modelClass): array
    {
        $hasSnakeKey = array_key_exists($snakeKey, $data);
        $hasCamelKey = array_key_exists($camelKey, $data);

        if (! $hasSnakeKey && ! $hasCamelKey) {
            return $data;
        }

        $value = $hasSnakeKey ? $data[$snakeKey] : $data[$camelKey];

        unset($data[$camelKey]);

        if ($value === null || $value === '') {
            $data[$snakeKey] = null;

            return $data;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages([
                $snakeKey => self::invalidMessage($snakeKey),
            ]);
        }

        $data[$snakeKey] = self::resolveOwnedId($modelClass, (string) $value, $snakeKey);

        return $data;
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     */
    private static function resolveOwnedId(string $modelClass, string $id, string $field): string
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $id;
        }

        try {
            return (string) OwnerWriteGuard::findOrFailForOwner(
                $modelClass,
                $id,
                includeGlobal: (bool) config('affiliates.owner.include_global', false),
                message: self::accessDeniedMessage($field),
            )->getKey();
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            throw ValidationException::withMessages([
                $field => self::accessDeniedMessage($field),
            ]);
        }
    }

    private static function accessDeniedMessage(string $field): string
    {
        return 'The selected ' . str_replace('_id', '', $field) . ' is not accessible in the current owner scope.';
    }

    private static function invalidMessage(string $field): string
    {
        return 'The selected ' . str_replace('_id', '', $field) . ' is invalid.';
    }
}
