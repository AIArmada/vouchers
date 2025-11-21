<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $currency
 * @property int $discount_amount
 * @property string $channel
 * @property array<string, mixed>|null $target_definition
 * @property string $redeemed_by_type
 * @property string $cart_identifier
 * @property string $user_identifier
 * @property string|null $notes
 * @property string $redeemed_by_id
 * @property array<string, mixed>|null $cart_snapshot
 * @property \Carbon\Carbon $used_at
 */
class VoucherUsage extends Model
{
    use HasUuids;

    public const CHANNEL_AUTOMATIC = 'automatic';

    public const CHANNEL_MANUAL = 'manual';

    public const CHANNEL_API = 'api';

    public $timestamps = false;

    protected $fillable = [
        'voucher_id',
        'discount_amount',
        'currency',
        'channel',
        'notes',
        'metadata',
        'redeemed_by_type',
        'redeemed_by_id',
        'used_at',
        'target_definition',
    ];

    public function getTable(): string
    {
        return config('vouchers.table_names.voucher_usage', 'voucher_usage');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function redeemedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function isManual(): bool
    {
        return $this->getAttribute('channel') === self::CHANNEL_MANUAL;
    }

    protected function userIdentifier(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function (): string {
                $redeemedBy = $this->redeemedBy;

                if (! $redeemedBy) {
                    return 'N/A';
                }

                // If it's a user model, return email
                if ($this->redeemed_by_type === 'user' && method_exists($redeemedBy, 'getAttribute')) {
                    return $redeemedBy->getAttribute('email') ?? 'N/A';
                }

                // For other types, try to get an identifier
                if (method_exists($redeemedBy, 'getAttribute')) {
                    return $redeemedBy->getAttribute('id') ?? 'N/A';
                }

                return 'N/A';
            }
        );
    }

    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer', // Stored as cents
            'metadata' => 'array',
            'target_definition' => 'array',
            'used_at' => 'datetime',
        ];
    }
}
