<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $voucher_id
 * @property string $currency
 * @property int $discount_amount
 * @property string $channel
 * @property array<string, mixed>|null $target_definition
 * @property string|null $redeemed_by_type
 * @property string|null $redeemed_by_id
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $cart_snapshot
 * @property Carbon $used_at
 * @property-read Voucher $voucher
 * @property-read Model|null $redeemedBy
 * @property-read string $user_identifier
 * @property-read string $cart_identifier
 */
final class VoucherUsage extends Model
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
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['voucher_usage'] ?? $prefix . 'voucher_usage';
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
    public function redeemedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function isManual(): bool
    {
        return $this->getAttribute('channel') === self::CHANNEL_MANUAL;
    }

    protected function userIdentifier(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $redeemedBy = $this->redeemedBy;

                if (! $redeemedBy) {
                    return 'N/A';
                }

                // If it's a user model, return email
                if ($this->redeemed_by_type === 'user' && method_exists($redeemedBy, 'getAttribute')) {
                    /** @var string|null $email */
                    $email = $redeemedBy->getAttribute('email');

                    return $email ?? 'N/A';
                }

                // For other types, try to get an identifier
                if (method_exists($redeemedBy, 'getAttribute')) {
                    /** @var string|int|null $id */
                    $id = $redeemedBy->getAttribute('id');

                    return $id !== null ? (string) $id : 'N/A';
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
