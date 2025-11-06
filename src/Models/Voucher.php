<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property VoucherType $type
 * @property int $value Value in cents for fixed amounts, or basis points for percentage (e.g., 10.50% = 1050)
 * @property string $currency
 * @property int|null $min_cart_value Value in cents
 * @property int|null $max_discount Value in cents
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_user
 * @property int $times_used
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property VoucherStatus $status
 * @property array<mixed>|null $applicable_products
 * @property array<mixed>|null $excluded_products
 * @property array<mixed>|null $applicable_categories
 * @property array<mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Voucher extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'currency',
        'min_cart_value',
        'max_discount',
        'usage_limit',
        'usage_limit_per_user',
        'times_used',
        'allows_manual_redemption',
        'starts_at',
        'expires_at',
        'status',
        'applicable_products',
        'excluded_products',
        'applicable_categories',
        'metadata',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        return config('vouchers.table_names.vouchers', 'vouchers');
    }

    public function usages(): HasMany
    {
        /** @var HasMany<VoucherUsage, Voucher> $relation */
        $relation = $this->hasMany(VoucherUsage::class);

        return $relation;
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('vouchers.owner.enabled', false)) {
            return $query;
        }

        if (! $owner) {
            return $includeGlobal
                ? $query->whereNull('owner_id')
                : $query->whereNull('owner_type')->whereNull('owner_id');
        }

        return $query->where(function (Builder $builder) use ($owner, $includeGlobal): void {
            $builder->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
    }

    public function allowsManualRedemption(): bool
    {
        return (bool) $this->getAttribute('allows_manual_redemption');
    }

    public function isActive(): bool
    {
        /** @var VoucherStatus|null $status */
        $status = $this->getAttribute('status');

        return $status === VoucherStatus::Active;
    }

    public function isExpired(): bool
    {
        /** @var \Illuminate\Support\Carbon|null $expiresAt */
        $expiresAt = $this->getAttribute('expires_at');

        return $expiresAt !== null && $expiresAt->isPast();
    }

    public function hasStarted(): bool
    {
        /** @var \Illuminate\Support\Carbon|null $startsAt */
        $startsAt = $this->getAttribute('starts_at');

        return $startsAt === null || $startsAt->isPast();
    }

    public function hasUsageLimitRemaining(): bool
    {
        $usageLimit = $this->getAttribute('usage_limit');

        if (! $usageLimit) {
            return true;
        }

        return $this->getAttribute('times_used') < $usageLimit;
    }

    public function getRemainingUses(): ?int
    {
        $usageLimit = $this->getAttribute('usage_limit');

        if (! $usageLimit) {
            return null;
        }

        return max(0, $usageLimit - $this->getAttribute('times_used'));
    }

    public function incrementUsage(): void
    {
        $this->increment('times_used');

        // Auto-update status if depleted
        $usageLimit = $this->getAttribute('usage_limit');

        if ($usageLimit && $this->getAttribute('times_used') >= $usageLimit) {
            $this->update(['status' => VoucherStatus::Depleted]);
        }
    }

    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'status' => VoucherStatus::class,
            'value' => 'integer', // Stored as cents or basis points
            'min_cart_value' => 'integer', // Stored as cents
            'max_discount' => 'integer', // Stored as cents
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'times_used' => 'integer',
            'allows_manual_redemption' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'applicable_products' => 'array',
            'excluded_products' => 'array',
            'applicable_categories' => 'array',
            'metadata' => 'array',
        ];
    }
}
