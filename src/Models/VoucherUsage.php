<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Support\LogOptions;

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
 * @property CarbonImmutable $used_at
 * @property-read Voucher $voucher
 * @property-read Model|null $redeemedBy
 * @property-read string $user_identifier
 * @property-read string $cart_identifier
 */
final class VoucherUsage extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasUuids;
    use LogsCommerceActivity;

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

    public function isOrderRedemption(): bool
    {
        $redeemedBy = $this->resolveRedeemedBySafely();

        $redeemedByType = mb_strtolower((string) ($this->redeemed_by_type ?? ''));

        if ($redeemedByType !== '' && Str::contains($redeemedByType, 'order')) {
            return true;
        }

        if (! $redeemedBy) {
            return false;
        }

        return Str::contains(mb_strtolower($redeemedBy::class), 'order');
    }

    protected function userIdentifier(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $redeemedBy = $this->resolveRedeemedBySafely();

                if (! $redeemedBy) {
                    return 'N/A';
                }

                // Prefer email when available, regardless of morph alias/class naming.
                $email = $this->getLoadedStringAttribute($redeemedBy, 'email');

                if ($email !== null) {
                    return $email;
                }

                if ($this->isOrderRedemption()) {
                    $orderNumber = $this->getLoadedStringAttribute($redeemedBy, 'order_number');

                    if ($orderNumber !== null) {
                        return $orderNumber;
                    }
                }

                $identifier = $redeemedBy->getKey();

                if ($identifier !== null && $identifier !== '') {
                    return (string) $identifier;
                }

                return 'N/A';
            }
        );
    }

    private function resolveRedeemedBySafely(): ?Model
    {
        if (! $this->relationLoaded('redeemedBy')) {
            return null;
        }

        $relation = $this->getRelation('redeemedBy');

        return $relation instanceof Model ? $relation : null;
    }

    private function getLoadedStringAttribute(Model $model, string $attribute): ?string
    {
        $attributes = $model->getAttributes();

        if (! array_key_exists($attribute, $attributes)) {
            return null;
        }

        $value = $attributes[$attribute];

        if ($value === null) {
            return null;
        }

        $stringValue = (string) $value;

        return $stringValue !== '' ? $stringValue : null;
    }

    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer', // Stored as cents
            'metadata' => 'array',
            'target_definition' => 'array',
            'used_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getAuditInclude(): array
    {
        return [
            'voucher_id',
            'discount_amount',
            'currency',
            'channel',
            'target_definition',
            'redeemed_by_type',
            'redeemed_by_id',
            'used_at',
            'notes',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'voucher_id',
                'discount_amount',
                'currency',
                'channel',
                'target_definition',
                'redeemed_by_type',
                'redeemed_by_id',
                'used_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('vouchers');
    }
}
