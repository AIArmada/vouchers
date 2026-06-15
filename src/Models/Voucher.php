<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Paused;
use AIArmada\Vouchers\States\VoucherStatus;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property VoucherType $type
 * @property int $value Value in cents for fixed amounts, or basis points for percentage (e.g., 10.50% = 1050)
 * @property array<string, mixed>|null $value_config Configuration for compound voucher types (BOGO, Tiered, Bundle, Cashback)
 * @property string|null $credit_destination Destination for cashback credits (wallet, next_order, points)
 * @property int $credit_delay_hours Hours to wait before crediting cashback
 * @property string $currency
 * @property int|null $min_cart_value Value in cents
 * @property int|null $max_discount Value in cents
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_user
 * @property int $applied_count Number of times the voucher has been applied to carts
 * @property bool $allows_manual_redemption
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $paused_at
 * @property CarbonImmutable|null $depleted_at
 * @property CarbonImmutable|null $last_activated_at
 * @property VoucherStatus $status
 * @property array<string, mixed>|null $target_definition
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $stacking_rules
 * @property array<string>|null $exclusion_groups
 * @property int $stacking_priority
 * @property string|null $promotion_id
 * @property string|null $affiliate_id
 * @property string|null $affiliate_program_id
 * @property CommissionType|null $affiliate_commission_type
 * @property int|null $affiliate_commission_value
 * @property array<array{level:int, share:float}>|null $affiliate_upline_levels
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $times_used
 * @property-read float|null $usageProgress
 * @property-read string|null $owner_display_name
 * @property-read int|null $remaining_uses
 * @property-read string $value_label
 * @property-read int $wallet_entries_count
 * @property-read int $wallet_claimed_count
 * @property-read int $wallet_redeemed_count
 * @property-read int $wallet_available_count
 * @property-read Affiliate|null $affiliate
 * @property-read AffiliateProgram|null $affiliateProgram
 * @property-read Model|null $promotion
 */
class Voucher extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasStates;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'vouchers.owner';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'value_config',
        'credit_destination',
        'credit_delay_hours',
        'currency',
        'min_cart_value',
        'max_discount',
        'usage_limit',
        'usage_limit_per_user',
        'applied_count',
        'allows_manual_redemption',
        'starts_at',
        'expires_at',
        'paused_at',
        'depleted_at',
        'last_activated_at',
        'status',
        'metadata',
        'owner_type',
        'owner_id',
        'target_definition',
        'stacking_rules',
        'exclusion_groups',
        'stacking_priority',
        'promotion_id',
        'affiliate_id',
        'affiliate_program_id',
        'affiliate_commission_type',
        'affiliate_commission_value',
        'affiliate_upline_levels',
    ];

    public function getTable(): string
    {
        $tables = config('vouchers.database.tables', []);
        $prefix = config('vouchers.database.table_prefix', '');

        return $tables['vouchers'] ?? $prefix.'vouchers';
    }

    /**
     * @return HasMany<VoucherUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    /**
     * @return HasMany<VoucherWallet, $this>
     */
    public function walletEntries(): HasMany
    {
        return $this->hasMany(VoucherWallet::class);
    }

    /**
     * @return HasMany<VoucherTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(VoucherTransaction::class);
    }

    /**
     * Get the promotion that issued this voucher when the promotions package is installed.
     *
     * @return BelongsTo<Model, $this>
     */
    public function promotion(): BelongsTo
    {
        $promotionClass = '\\AIArmada\\Promotions\\Models\\Promotion';

        if (class_exists($promotionClass)) {
            /** @var class-string<Model> $promotionClass */

            return $this->belongsTo($promotionClass, 'promotion_id');
        }

        return $this->belongsTo(Model::class, 'promotion_id');
    }

    /**
     * Get the affiliate that owns this voucher (when aiarmada/affiliates is installed).
     *
     * @return BelongsTo<Affiliate, $this>|BelongsTo<Model, $this>
     */
    public function affiliate(): BelongsTo
    {
        if (\class_exists(Affiliate::class)) {
            return $this->belongsTo(Affiliate::class, 'affiliate_id');
        }

        return $this->belongsTo(Model::class, 'affiliate_id');
    }

    /**
     * @return BelongsTo<AffiliateProgram, $this>|BelongsTo<Model, $this>
     */
    public function affiliateProgram(): BelongsTo
    {
        if (! class_exists(AffiliateProgram::class)) {
            return $this->belongsTo(Model::class, 'affiliate_program_id');
        }

        return $this->belongsTo(AffiliateProgram::class, 'affiliate_program_id');
    }

    /**
     * Check if this voucher belongs to an affiliate.
     */
    public function belongsToAffiliate(): bool
    {
        return $this->affiliate_id !== null;
    }

    /**
     * Scope to filter vouchers by affiliate.
     *
     * @param  Builder<Voucher>  $query
     * @return Builder<Voucher>
     */
    public function scopeForAffiliate(Builder $query, string $affiliateId): Builder
    {
        return $query->where('affiliate_id', $affiliateId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = false): Builder
    {
        if (! config('vouchers.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('vouchers.owner.include_global', false);

        return $this->baseScopeForOwner($query, $owner, $includeGlobal);
    }

    public function allowsManualRedemption(): bool
    {
        return (bool) $this->getAttribute('allows_manual_redemption');
    }

    public function isActive(): bool
    {
        return $this->status instanceof Active;
    }

    public function isExpired(): bool
    {
        /** @var Carbon|null $expiresAt */
        $expiresAt = $this->getAttribute('expires_at');

        return $expiresAt !== null && $expiresAt->isPast();
    }

    public function hasStarted(): bool
    {
        /** @var Carbon|null $startsAt */
        $startsAt = $this->getAttribute('starts_at');

        return $startsAt === null || $startsAt->isPast();
    }

    public function hasUsageLimitRemaining(): bool
    {
        $usageLimit = $this->getAttribute('usage_limit');

        if (! $usageLimit) {
            return true;
        }

        return $this->times_used < $usageLimit;
    }

    public function getRemainingUses(): ?int
    {
        /** @var int|null $usageLimit */
        $usageLimit = $this->getAttribute('usage_limit');

        if ($usageLimit === null) {
            return null;
        }

        return max(0, $usageLimit - $this->times_used);
    }

    /**
     * Check if voucher should be marked as depleted and update status if so.
     *
     * Note: This does NOT increment usage - that's handled by VoucherUsage records.
     * Call this after recording usage to auto-update status when limit is reached.
     */
    public function checkIfDepleted(): void
    {
        $usageLimit = $this->getAttribute('usage_limit');

        if ($usageLimit && $this->getTimesUsedAttribute() >= $usageLimit) {
            $this->update(['status' => Depleted::class]);
        }
    }

    /**
     * Get the conversion rate (redeemed vs applied).
     * Returns the percentage of applications that resulted in actual usage/redemption.
     *
     * @return float|null Conversion rate as percentage (0-100), or null if never applied
     */
    public function getConversionRate(): ?float
    {
        /** @var int $appliedCount */
        $appliedCount = $this->getAttribute('applied_count') ?? 0;

        if ($appliedCount === 0) {
            return null;
        }

        $usageCount = $this->times_used;

        return ($usageCount / $appliedCount) * 100;
    }

    /**
     * Get the number of times this voucher was applied but not redeemed.
     *
     * @return int Number of abandoned applications
     */
    public function getAbandonedCount(): int
    {
        /** @var int $appliedCount */
        $appliedCount = $this->getAttribute('applied_count') ?? 0;
        $usageCount = $this->times_used;

        return (int) max(0, $appliedCount - $usageCount);
    }

    /**
     * Get comprehensive statistics for this voucher.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        /** @var int $appliedCount */
        $appliedCount = $this->getAttribute('applied_count') ?? 0;
        $usageCount = $this->times_used;

        return [
            'applied_count' => $appliedCount,
            'redeemed_count' => $usageCount,
            'abandoned_count' => $this->getAbandonedCount(),
            'conversion_rate' => $this->getConversionRate(),
            'remaining_uses' => $this->getRemainingUses(),
        ];
    }

    public function getTimesUsedAttribute(): int
    {
        if (array_key_exists('usages_count', $this->attributes)) {
            $usagesCount = $this->attributes['usages_count'];

            return is_numeric($usagesCount) ? (int) $usagesCount : 0;
        }

        if ($this->relationLoaded('usages')) {
            return $this->usages->count();
        }

        return $this->usages()->count();
    }

    public function canBeRedeemed(): bool
    {
        return $this->isActive()
            && $this->hasStarted()
            && ! $this->isExpired()
            && $this->hasUsageLimitRemaining();
    }

    /**
     * Check if this voucher can stack with another voucher.
     *
     * Compares exclusion groups to determine if vouchers are mutually exclusive.
     */
    public function canStackWith(self $other): bool
    {
        $myGroups = $this->exclusion_groups ?? [];
        $otherGroups = $other->exclusion_groups ?? [];

        if (empty($myGroups) || empty($otherGroups)) {
            return true;
        }

        return empty(array_intersect($myGroups, $otherGroups));
    }

    /**
     * Get the stacking priority for ordering.
     */
    public function getStackingPriority(): int
    {
        return $this->stacking_priority ?? 100;
    }

    public function getUsageProgressAttribute(): ?float
    {
        /** @var int|null $usageLimit */
        $usageLimit = $this->getAttribute('usage_limit');

        if (! $usageLimit) {
            return null;
        }

        $timesUsed = $this->getTimesUsedAttribute();

        return min(100, ($timesUsed / $usageLimit) * 100);
    }

    public function getOwnerDisplayNameAttribute(): ?string
    {
        $owner = $this->owner;

        if (! $owner) {
            return null;
        }

        if (method_exists($owner, 'getAttribute')) {
            /** @var string|null $name */
            $name = $owner->getAttribute('name');
            /** @var string|null $displayName */
            $displayName = $owner->hasAttribute('display_name') ? $owner->getAttribute('display_name') : null;
            /** @var string|null $email */
            $email = $owner->getAttribute('email');
            /** @var int|string $key */
            $key = $owner->getKey();

            return $name ?? $displayName ?? $email ?? class_basename($owner).':'.(string) $key;
        }

        /** @var int|string $key */
        $key = $owner->getKey();

        return class_basename($owner).':'.(string) $key;
    }

    public function getRemainingUsesAttribute(): ?int
    {
        return $this->getRemainingUses();
    }

    /**
     * Get the human-readable value label (e.g., "10.50 %" or "RM 50.00").
     */
    public function getValueLabelAttribute(): string
    {
        $value = (int) $this->getAttribute('value');
        $type = $this->getAttribute('type');

        $enumType = $type instanceof VoucherType ? $type : VoucherType::tryFrom((string) $type);

        if ($enumType === VoucherType::Percentage) {
            // Value is stored in basis points (e.g., 1000 = 10.00%, 1259 = 12.59%)
            $percentage = $value / 100;

            return mb_rtrim(mb_rtrim(number_format($percentage, 2), '0'), '.').' %';
        }

        // Value is stored as cents
        $currency = (string) ($this->getAttribute('currency') ?? config('vouchers.default_currency', 'MYR'));

        return MoneyFormatter::formatMinor($value, $currency);
    }

    /**
     * Get the total number of wallet entries for this voucher.
     */
    public function getWalletEntriesCountAttribute(): int
    {
        $count = $this->attributes['wallet_entries_count'] ?? null;

        return $count !== null ? (int) $count : $this->walletEntries()->count();
    }

    /**
     * Get the number of claimed wallet entries.
     */
    public function getWalletClaimedCountAttribute(): int
    {
        $count = $this->attributes['wallet_claimed_count'] ?? null;

        return $count !== null ? (int) $count : $this->walletEntries()->whereNotNull('claimed_at')->count();
    }

    /**
     * Get the number of redeemed wallet entries.
     */
    public function getWalletRedeemedCountAttribute(): int
    {
        $count = $this->attributes['wallet_redeemed_count'] ?? null;

        return $count !== null ? (int) $count : $this->walletEntries()->whereNotNull('redeemed_at')->count();
    }

    /**
     * Get the number of available (not redeemed) wallet entries.
     */
    public function getWalletAvailableCountAttribute(): int
    {
        $count = $this->attributes['wallet_available_count'] ?? null;

        return $count !== null ? (int) $count : $this->walletEntries()->whereNull('redeemed_at')->count();
    }

    public function getPromotionSourceIdAttribute(): ?string
    {
        if (class_exists('\\AIArmada\\Promotions\\Models\\Promotion')) {
            $promotion = $this->promotion;

            if ($promotion !== null && $promotion->getKey() !== null) {
                return (string) $promotion->getKey();
            }
        }

        return $this->normalizePromotionSourceString(
            data_get($this->metadata, 'source_promotion_id', $this->promotion_id)
        );
    }

    public function getPromotionSourceNameAttribute(): ?string
    {
        if (class_exists('\\AIArmada\\Promotions\\Models\\Promotion')) {
            $promotion = $this->promotion;

            if ($promotion !== null) {
                return $this->normalizePromotionSourceString($promotion->getAttribute('name'));
            }
        }

        return $this->normalizePromotionSourceString(data_get($this->metadata, 'source_promotion_name'));
    }

    public function getPromotionSourceCodeAttribute(): ?string
    {
        if (class_exists('\\AIArmada\\Promotions\\Models\\Promotion')) {
            $promotion = $this->promotion;

            if ($promotion !== null) {
                return $this->normalizePromotionSourceString($promotion->getAttribute('code'));
            }
        }

        return $this->normalizePromotionSourceString(data_get($this->metadata, 'source_promotion_code'));
    }

    public function getPromotionSourceLabelAttribute(): ?string
    {
        $name = $this->promotion_source_name;
        $code = $this->promotion_source_code;

        if ($name !== null && $code !== null) {
            return $name.' ('.$code.')';
        }

        return $name ?? $code;
    }

    private function normalizePromotionSourceString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = mb_trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected static function booted(): void
    {
        self::saving(function (Voucher $voucher): void {
            $voucher->syncAffiliateMetadataFromAffiliateId();

            if ($voucher->isDirty('status')) {
                $originalStatus = $voucher->getOriginal('status');

                if ($voucher->status instanceof Paused && (! $originalStatus instanceof Paused)) {
                    $voucher->paused_at = CarbonImmutable::now();
                } elseif ($voucher->status instanceof Active && (! $originalStatus instanceof Active)) {
                    $voucher->last_activated_at = CarbonImmutable::now();
                    $voucher->paused_at = null;
                } elseif ($voucher->status instanceof Depleted && (! $originalStatus instanceof Depleted)) {
                    $voucher->depleted_at = CarbonImmutable::now();
                }
            }
        });

        self::creating(function (Voucher $voucher): void {
            if ($voucher->status instanceof Active) {
                $voucher->last_activated_at = CarbonImmutable::now();
            }
        });

        self::deleting(function (Voucher $voucher): void {
            $voucher->usages()->delete();
            $voucher->walletEntries()->delete();
            $voucher->transactions()->delete();
        });
    }

    private function syncAffiliateMetadataFromAffiliateId(): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        $affiliateId = $this->affiliate_id;

        if (is_string($affiliateId) && $affiliateId !== '') {
            $metadata['affiliate_id'] = $affiliateId;

            $affiliate = $this->resolveAffiliateForMetadataSync($affiliateId);

            if ($affiliate instanceof Affiliate) {
                $metadata['affiliate_code'] = (string) $affiliate->code;
            } else {
                unset($metadata['affiliate_code']);
            }
        } else {
            unset(
                $metadata['affiliate_id'],
                $metadata['affiliate_code'],
            );
        }

        $this->syncAffiliateMetadataValue($metadata, 'affiliate_program_id', $this->affiliate_program_id);
        $this->syncAffiliateMetadataValue(
            $metadata,
            'affiliate_commission_type',
            $this->normalizeAffiliateCommissionType($this->affiliate_commission_type)
        );
        $this->syncAffiliateMetadataValue($metadata, 'affiliate_commission_value', $this->affiliate_commission_value);
        $this->syncAffiliateMetadataValue($metadata, 'affiliate_upline_levels', $this->affiliate_upline_levels);

        $this->metadata = $metadata !== [] ? $metadata : null;
    }

    private function resolveAffiliateForMetadataSync(string $affiliateId): ?Affiliate
    {
        if (! class_exists(Affiliate::class)) {
            return null;
        }

        $query = Affiliate::query()->whereKey($affiliateId);

        if (
            is_string($this->owner_type)
            && $this->owner_type !== ''
            && $this->owner_id !== null
            && $this->owner_id !== ''
        ) {
            $query
                ->where('owner_type', $this->owner_type)
                ->where('owner_id', $this->owner_id);
        }

        $affiliate = $query->first();

        return $affiliate instanceof Affiliate ? $affiliate : null;
    }

    protected function casts(): array
    {
        $casts = [
            'type' => VoucherType::class,
            'status' => VoucherStatus::class,
            'value' => 'integer', // Stored as cents or basis points
            'value_config' => 'array',
            'credit_delay_hours' => 'integer',
            'min_cart_value' => 'integer', // Stored as cents
            'max_discount' => 'integer', // Stored as cents
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'applied_count' => 'integer',
            'allows_manual_redemption' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'depleted_at' => 'immutable_datetime',
            'last_activated_at' => 'immutable_datetime',
            'metadata' => 'array',
            'target_definition' => 'array',
            'stacking_rules' => 'array',
            'exclusion_groups' => 'array',
            'stacking_priority' => 'integer',
            'affiliate_commission_value' => 'integer',
            'affiliate_upline_levels' => 'array',
        ];

        if (class_exists(CommissionType::class)) {
            $casts['affiliate_commission_type'] = CommissionType::class;
        }

        return $casts;
    }

    /**
     * @return array<int, string>
     */
    public function getAuditInclude(): array
    {
        return [
            'code',
            'name',
            'type',
            'value',
            'value_config',
            'credit_destination',
            'credit_delay_hours',
            'currency',
            'min_cart_value',
            'max_discount',
            'usage_limit',
            'usage_limit_per_user',
            'applied_count',
            'allows_manual_redemption',
            'starts_at',
            'expires_at',
            'paused_at',
            'depleted_at',
            'last_activated_at',
            'status',
            'target_definition',
            'stacking_rules',
            'exclusion_groups',
            'stacking_priority',
            'promotion_id',
            'affiliate_id',
            'affiliate_program_id',
            'affiliate_commission_type',
            'affiliate_commission_value',
            'affiliate_upline_levels',
            'owner_type',
            'owner_id',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'code',
                'name',
                'type',
                'value',
                'currency',
                'usage_limit',
                'usage_limit_per_user',
                'applied_count',
                'allows_manual_redemption',
                'starts_at',
                'expires_at',
                'paused_at',
                'depleted_at',
                'last_activated_at',
                'status',
                'promotion_id',
                'affiliate_id',
                'affiliate_program_id',
                'affiliate_commission_type',
                'affiliate_commission_value',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('vouchers');
    }

    private function normalizeAffiliateCommissionType(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function syncAffiliateMetadataValue(array &$metadata, string $key, mixed $value): void
    {
        if ($value === null || $value === []) {
            unset($metadata[$key]);

            return;
        }

        $metadata[$key] = $value;
    }
}
