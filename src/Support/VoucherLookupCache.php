<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Models\Voucher;
use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Caches owner-scoped voucher lookups without caching negative results.
 *
 * Eloquent model writes invalidate the key through Voucher model events.
 * Integrations that write outside Eloquent must call VoucherService::invalidate()
 * after their write; until then, a cached lookup remains stale by contract.
 */
final class VoucherLookupCache
{
    use NormalizesVoucherCodes;

    /**
     * @param  Closure(): ?VoucherData  $resolver
     */
    public function remember(
        string $code,
        Model | OwnerScopeIdentifiable | null $owner,
        bool $includeGlobal,
        Closure $resolver,
    ): ?VoucherData {
        if ($includeGlobal) {
            return $resolver();
        }

        $ttl = (int) config('vouchers.cache.lookup_ttl', 300);

        if ($ttl <= 0) {
            return $resolver();
        }

        $logicalKey = $this->logicalKey($code);
        $cached = OwnerCache::get($owner, $logicalKey);

        if ($cached instanceof VoucherData) {
            return $cached;
        }

        $voucher = $resolver();

        if ($voucher instanceof VoucherData) {
            OwnerCache::put($owner, $logicalKey, $voucher, $ttl);
        }

        return $voucher;
    }

    public function forget(string $code, Model | OwnerScopeIdentifiable | null $owner): void
    {
        OwnerCache::forget($owner, $this->logicalKey($code));
    }

    public function forgetForVoucher(Voucher $voucher, ?string $code = null): void
    {
        $code ??= (string) $voucher->getAttribute('code');

        if ($code === '') {
            return;
        }

        OwnerCache::forget($this->ownerFromVoucher($voucher), $this->logicalKey($code));
    }

    private function logicalKey(string $code): string
    {
        return 'vouchers.lookup.' . hash('sha256', $this->normalizeCode($code));
    }

    private function ownerFromVoucher(Voucher $voucher): ?OwnerScopeIdentifiable
    {
        if (! config('vouchers.owner.enabled', false)) {
            return null;
        }

        /** @var string|null $ownerType */
        $ownerType = $voucher->getAttribute('owner_type');

        /** @var string|int|null $ownerId */
        $ownerId = $voucher->getAttribute('owner_id');

        if ($ownerType === null && $ownerId === null) {
            return null;
        }

        if (
            $ownerType === null
            || $ownerId === null
            || $ownerType === ''
            || (is_string($ownerId) && $ownerId === '')
        ) {
            throw new InvalidArgumentException('Voucher owner type and owner id must be either both null or both non-empty.');
        }

        return new class($ownerType, $ownerId) implements OwnerScopeIdentifiable
        {
            public function __construct(
                private readonly string $type,
                private readonly string | int $id,
            ) {}

            public function getMorphClass(): string
            {
                return $this->type;
            }

            public function getKey(): string | int
            {
                return $this->id;
            }
        };
    }
}
