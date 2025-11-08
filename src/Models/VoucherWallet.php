<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $voucher_id
 * @property string $owner_type
 * @property string $owner_id
 * @property bool $is_claimed
 * @property \Illuminate\Support\Carbon|null $claimed_at
 * @property bool $is_redeemed
 * @property \Illuminate\Support\Carbon|null $redeemed_at
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class VoucherWallet extends Model
{
    use HasUuids;

    protected $fillable = [
        'voucher_id',
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
        return config('vouchers.table_names.voucher_wallets', 'voucher_wallets');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function owner(): MorphTo
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
        return $this->voucher->isExpired();
    }

    public function canBeUsed(): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if (! $this->voucher->isActive()) {
            return false;
        }

        if (! $this->voucher->hasStarted()) {
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
