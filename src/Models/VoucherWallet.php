<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\CarbonImmutable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $voucher_id
 * @property string|null $holder_type
 * @property string|null $holder_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property CarbonImmutable|null $claimed_at
 * @property CarbonImmutable|null $redeemed_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Voucher $voucher
 * @property-read Model|null $holder
 * @property-read Model|null $owner
 */
final class VoucherWallet extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'vouchers.owner';

    protected $fillable = [
        'voucher_id',
        'holder_type',
        'holder_id',
        'owner_type',
        'owner_id',
        'claimed_at',
        'redeemed_at',
        'metadata',
    ];

    public function getTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['voucher_wallets'] ?? $prefix . 'voucher_wallets';
    }

    /**
     * Null-out the wallet FK on any transactions when this wallet is deleted.
     * VoucherTransaction.voucher_wallet_id is a nullable FK (no DB cascade),
     * so application code must handle the orphan-prevention here.
     */
    protected static function booted(): void
    {
        static::deleting(function (VoucherWallet $wallet): void {
            $wallet->transactions()->update(['voucher_wallet_id' => null]);
        });
    }

    /**
     * @return HasMany<VoucherTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(VoucherTransaction::class, 'voucher_wallet_id');
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function claim(): void
    {
        if ($this->claimed_at !== null) {
            return;
        }

        $this->update([
            'claimed_at' => CarbonImmutable::now(),
        ]);
    }

    public function markAsRedeemed(): void
    {
        if ($this->redeemed_at !== null) {
            return;
        }

        $this->update([
            'redeemed_at' => CarbonImmutable::now(),
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->claimed_at !== null && $this->redeemed_at === null;
    }

    public function isExpired(): bool
    {
        /** @var Voucher|null $voucher */
        $voucher = $this->voucher;

        return $voucher !== null && $voucher->isExpired();
    }

    public function canBeUsed(): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        /** @var Voucher|null $voucher */
        $voucher = $this->voucher;

        if ($voucher === null || ! $voucher->isActive()) {
            return false;
        }

        if (! $voucher->hasStarted()) {
            return false;
        }

        return true;
    }

    protected function casts(): array
    {
        return [
            'claimed_at' => 'immutable_datetime',
            'redeemed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getAuditInclude(): array
    {
        return [
            'voucher_id',
            'holder_type',
            'holder_id',
            'owner_type',
            'owner_id',
            'claimed_at',
            'redeemed_at',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'voucher_id',
                'holder_type',
                'holder_id',
                'claimed_at',
                'redeemed_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('vouchers');
    }
}
