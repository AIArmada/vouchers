<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Services;

use AIArmada\Vouchers\Actions\RecordVoucherUsage;
use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Exceptions\ManualRedemptionNotAllowedException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\States\Active;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class VoucherService implements VoucherServiceInterface
{
    use QueriesVouchers;

    public function __construct(
        protected VoucherValidator $validator
    ) {}

    public function find(string $code): ?VoucherData
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        return $voucher ? VoucherData::fromModel($voucher) : null;
    }

    public function findOrFail(string $code): VoucherData
    {
        $voucher = $this->find($code);

        if (! $voucher) {
            throw VoucherNotFoundException::withCode($code);
        }

        return $voucher;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): VoucherData
    {
        /** @var string $code */
        $code = $data['code'];
        $data['code'] = $this->normalizeCode($code);
        $data['status'] ??= Active::class;

        if (
            config('vouchers.owner.enabled', false)
            && config('vouchers.owner.auto_assign_on_create', true)
        ) {
            $owner = $this->resolveOwner();

            if ($owner !== null) {
                // Defense-in-depth: never trust inbound owner columns when a
                // concrete owner context is resolved for this request.
                $data['owner_type'] = $owner->getMorphClass();
                $data['owner_id'] = $owner->getKey();
            }
        }

        $voucher = VoucherModel::create($data);

        return VoucherData::fromModel($voucher);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $code, array $data): VoucherData
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        if (isset($data['code'])) {
            /** @var string $newCode */
            $newCode = $data['code'];
            $data['code'] = $this->normalizeCode($newCode);
        }

        $voucher->update($data);

        /** @var VoucherModel $freshVoucher */
        $freshVoucher = $voucher->fresh();

        return VoucherData::fromModel($freshVoucher);
    }

    public function delete(string $code): bool
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        return $voucher !== null && $voucher->delete();
    }

    public function validate(string $code, mixed $cart): VoucherValidationResult
    {
        return $this->validator->validate($code, $cart);
    }

    public function isValid(string $code): bool
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        /** @var VoucherModel|null $voucher */
        if (! $voucher) {
            return false;
        }

        return $voucher->isActive()
            && $voucher->hasStarted()
            && ! $voucher->isExpired()
            && $voucher->hasUsageLimitRemaining();
    }

    public function canBeUsedBy(string $code, ?Model $user = null): bool
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return false;
        }

        if (! $voucher->usage_limit_per_user || ! $user) {
            return true;
        }

        $usageCount = VoucherUsage::where('voucher_id', $voucher->id)
            ->where('redeemed_by_type', $user->getMorphClass())
            ->where('redeemed_by_id', $user->getKey())
            ->count();

        return $usageCount < $voucher->usage_limit_per_user;
    }

    public function getRemainingUses(string $code): int
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return 0;
        }

        return $voucher->getRemainingUses() ?? PHP_INT_MAX;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordUsage(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null,
        ?VoucherModel $voucherModel = null
    ): void {
        RecordVoucherUsage::run(
            code: $code,
            discountAmount: $discountAmount,
            channel: $channel,
            metadata: $metadata,
            redeemedBy: $redeemedBy,
            notes: $notes,
            voucherModel: $voucherModel,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function redeemManually(
        string $code,
        Money $discountAmount,
        ?string $reference = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null
    ): void {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        if (
            config('vouchers.redemption.manual_requires_flag', true)
            && ! $voucher->allowsManualRedemption()
        ) {
            throw ManualRedemptionNotAllowedException::forVoucher($voucher->code);
        }

        /** @var string $channel */
        $channel = config('vouchers.redemption.manual_channel', 'manual');

        $this->recordUsage(
            code: $code,
            discountAmount: $discountAmount,
            channel: $channel,
            metadata: array_merge($metadata ?? [], ['reference' => $reference]),
            redeemedBy: $redeemedBy,
            notes: $notes,
            voucherModel: $voucher
        );
    }

    /**
     * @return EloquentCollection<int, VoucherUsage>
     */
    public function getUsageHistory(string $code): EloquentCollection
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return new EloquentCollection;
        }

        /** @var EloquentCollection<int, VoucherUsage> $result */
        $result = $voucher->usages()
            ->latest('used_at')
            ->get();

        return $result;
    }

    /**
     * Add a voucher to the owner's wallet.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function addToWallet(string $code, Model $holder, ?array $metadata = null): VoucherWallet
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        return VoucherWallet::create([
            'voucher_id' => $voucher->id,
            'holder_type' => $holder->getMorphClass(),
            'holder_id' => $holder->getKey(),
            'owner_type' => $voucher->owner_type,
            'owner_id' => $voucher->owner_id,
            'is_claimed' => true,
            'claimed_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Remove a voucher from the owner's wallet (if not redeemed).
     */
    public function removeFromWallet(string $code, Model $holder): bool
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        return VoucherWallet::where('voucher_id', $voucher->id)
            ->where('holder_type', $holder->getMorphClass())
            ->where('holder_id', $holder->getKey())
            ->where('is_redeemed', false)
            ->delete() > 0;
    }

    /**
     * Reserve a voucher for a checkout session.
     *
     * Reservations prevent the voucher from being used elsewhere
     * during the checkout process.
     */
    public function reserve(string $code, string $sessionId): void
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        $cacheKey = $this->reservationCacheKey((string) $voucher->id, $sessionId);
        $sessionsKey = $this->reservationSessionsCacheKey((string) $voucher->id);
        $ttl = config('vouchers.reservation.ttl', 900); // 15 minutes default

        Cache::put($cacheKey, [
            'voucher_id' => $voucher->id,
            'session_id' => $sessionId,
            'reserved_at' => now()->toIso8601String(),
        ], $ttl);

        $sessionIds = Cache::get($sessionsKey, []);

        if (! is_array($sessionIds)) {
            $sessionIds = [];
        }

        if (! in_array($sessionId, $sessionIds, true)) {
            $sessionIds[] = $sessionId;
        }

        Cache::put($sessionsKey, array_values($sessionIds), $ttl);
    }

    /**
     * Release a voucher reservation.
     */
    public function release(string $code): void
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return;
        }

        $sessionsKey = $this->reservationSessionsCacheKey((string) $voucher->id);
        $sessionIds = Cache::get($sessionsKey, []);

        if (is_array($sessionIds)) {
            foreach ($sessionIds as $sessionId) {
                if (is_string($sessionId) && $sessionId !== '') {
                    Cache::forget($this->reservationCacheKey((string) $voucher->id, $sessionId));
                }
            }
        }

        Cache::forget($sessionsKey);
    }

    /**
     * Redeem a voucher after successful order completion.
     */
    public function redeem(string $code, string $orderId): void
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        $currency = config('vouchers.default_currency', 'MYR');

        $voucherType = $voucher->type instanceof VoucherType
            ? $voucher->type
            : VoucherType::tryFrom((string) $voucher->type);

        // Calculate the discount amount based on voucher type
        $discountAmount = match ($voucherType) {
            VoucherType::Percentage => Money::{$currency}(0), // Will be updated with actual order discount
            VoucherType::Fixed => Money::{$currency}((int) ($voucher->value ?? 0)),
            default => Money::{$currency}(0),
        };

        $this->recordUsage(
            code: $code,
            discountAmount: $discountAmount,
            channel: 'checkout',
            metadata: ['order_id' => $orderId],
            voucherModel: $voucher
        );
    }

    private function reservationCacheKey(string $voucherId, string $sessionId): string
    {
        return "voucher_reservation:{$voucherId}:{$sessionId}";
    }

    private function reservationSessionsCacheKey(string $voucherId): string
    {
        return "voucher_reservation_sessions:{$voucherId}";
    }
}
