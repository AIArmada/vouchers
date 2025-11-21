<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VoucherTransaction extends Model
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

    protected $casts = [
        'amount' => 'integer',
        'balance' => 'integer',
        'currency' => 'string',
        'metadata' => 'array',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function voucherWallet(): BelongsTo
    {
        return $this->belongsTo(VoucherWallet::class);
    }

    public function walletable(): MorphTo
    {
        return $this->morphTo();
    }
}
