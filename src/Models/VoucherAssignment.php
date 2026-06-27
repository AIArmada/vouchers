<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $voucher_id
 * @property string $assignee_type
 * @property string $assignee_id
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable|null $expires_at
 * @property string $status
 * @property CarbonImmutable|null $revoked_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Voucher $voucher
 * @property-read Model $assignee
 */
final class VoucherAssignment extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasUuids;
    use LogsCommerceActivity;

    protected $fillable = [
        'voucher_id',
        'assignee_type',
        'assignee_id',
        'assigned_at',
        'expires_at',
        'status',
        'revoked_at',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    public function getTable(): string
    {
        $tables = config('vouchers.database.tables', []);
        $prefix = config('vouchers.database.table_prefix', '');

        return $tables['voucher_assignments'] ?? $prefix . 'voucher_assignments';
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
