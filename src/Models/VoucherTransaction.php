<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $voucher_id
 * @property string|null $voucher_wallet_id
 * @property string|null $walletable_type
 * @property string|null $walletable_id
 * @property int $amount
 * @property int $balance
 * @property string $type
 * @property string $currency
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Voucher $voucher
 * @property-read VoucherWallet|null $voucherWallet
 * @property-read Model|null $walletable
 */
final class VoucherTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'voucher_id',
        'voucher_wallet_id',
        'walletable_type',
        'walletable_id',
        'amount',
        'balance',
        'type',
        'currency',
        'description',
        'metadata',
    ];

    public function getTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['voucher_transactions'] ?? $prefix . 'voucher_transactions';
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<VoucherWallet, $this>
     */
    public function voucherWallet(): BelongsTo
    {
        return $this->belongsTo(VoucherWallet::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function walletable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance' => 'integer',
            'currency' => 'string',
            'metadata' => 'array',
        ];
    }
}
