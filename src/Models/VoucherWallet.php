<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $voucher_id
 * @property string|null $holder_type
 * @property string|null $holder_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property bool $is_claimed
 * @property Carbon|null $claimed_at
 * @property bool $is_redeemed
 * @property Carbon|null $redeemed_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Voucher $voucher
 * @property-read Model|null $holder
 * @property-read Model|null $owner
 */
final class VoucherWallet extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'vouchers.owner';

    protected $fillable = [
        'voucher_id',
        'holder_type',
        'holder_id',
        'owner_type',
        'owner_id',
        'is_claimed',
        'claimed_at',
        'is_redeemed',
        'redeemed_at',
        'metadata',
    ];

    protected $attributes = [
        'is_claimed' => false,
        'is_redeemed' => false,
    ];

    public function getTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['voucher_wallets'] ?? $prefix . 'voucher_wallets';
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
        if ($this->is_claimed) {
            return;
        }

        $this->update([
            'is_claimed' => true,
            'claimed_at' => now(),
        ]);
    }

    public function markAsRedeemed(): void
    {
        if ($this->is_redeemed) {
            return;
        }

        $this->update([
            'is_redeemed' => true,
            'redeemed_at' => now(),
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->is_claimed && ! $this->is_redeemed;
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
            'is_claimed' => 'boolean',
            'claimed_at' => 'datetime',
            'is_redeemed' => 'boolean',
            'redeemed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
