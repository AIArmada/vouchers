---
title: API Reference
---

# API Reference

This page is the low-level surface map for voucher facades, cart helpers, DTOs, enums, and events.

## Facade: Voucher

```php
use AIArmada\Vouchers\Facades\Voucher;
```

### CRUD Operations

#### find

Find a voucher by code.

```php
Voucher::find(string $code): ?VoucherData
```

Returns `VoucherData` or `null` if not found.

---

#### invalidate

Invalidate a cached lookup in the current owner scope. Eloquent voucher writes do this automatically; call it after an out-of-band provider write.

```php
Voucher::invalidate(string $code): void
```

---

#### findOrFail

Find a voucher by code or throw exception.

```php
Voucher::findOrFail(string $code): VoucherData
```

Throws `VoucherNotFoundException` if not found.

---

#### create

Create a new voucher.

```php
Voucher::create(array $data): VoucherData
```

**Parameters:**
- `code` (required) - Unique voucher code
- `name` (required) - Display name
- `type` (required) - VoucherType enum or string
- `value` (required) - Value in cents/basis points
- `currency` (required) - Currency code
- `description` - Optional description
- `min_cart_value` - Minimum cart value in cents
- `max_discount` - Maximum discount in cents
- `usage_limit` - Global usage limit
- `usage_limit_per_user` - Per-user usage limit
- `starts_at` - Start datetime
- `expires_at` - Expiry datetime
- `allows_manual_redemption` - Allow manual redemption
- `status` - Spatie state class, e.g. `AIArmada\Vouchers\States\Active::class` (default: `Active::class`). The backed `AIArmada\Vouchers\Enums\VoucherStatus` enum is **not** accepted here.
- `metadata` - Additional data array
- `target_definition` - Targeting rules array

> **warning**
> `status` is only rewritten when something explicitly transitions it. A voucher whose
> `expires_at` has passed can still read as `Active`. For anything user-facing, read the
> derived accessor instead:
>
> ```php
> $voucher->effective_status; // AIArmada\Vouchers\States\VoucherStatus
> ```
>
> `effective_status` returns `Expired` when `isExpired()` is true and the stored state is not
> already `Expired` or `Depleted`. This mirrors the redemption path, where `VoucherValidator`
> checks `isExpired()` *before* it reads status — so a past-due voucher cannot be redeemed even
> if its stored state still says `Active`. There is no nightly sweep to keep the column honest.

---

#### update

Update an existing voucher.

```php
Voucher::update(string $code, array $data): VoucherData
```

---

#### delete

Delete a voucher.

```php
Voucher::delete(string $code): bool
```

Returns `true` if deleted, `false` if not found.

---

### Validation

#### validate

Validate a voucher against a cart.

```php
Voucher::validate(string $code, mixed $cart): VoucherValidationResult
```

Returns `VoucherValidationResult` with:
- `isValid` - Boolean
- `reason` - String (when invalid)
- `details` - Array with extra context (when invalid)

---

#### getRemainingUses

Get remaining usage count.

```php
Voucher::getRemainingUses(string $code): int
```

Returns `0` if voucher not found, `PHP_INT_MAX` if no limit.

---

### Usage Tracking

#### recordUsage

Record a voucher usage.

```php
Voucher::recordUsage(
    string $code,
    Money $discountAmount,
    ?string $channel = null,
    ?array $metadata = null,
    ?Model $redeemedBy = null,
    ?string $notes = null,
    ?VoucherModel $voucherModel = null
): void
```

---

#### redeemManually

Manually redeem a voucher outside cart flow.

```php
Voucher::redeemManually(
    string $code,
    Money $discountAmount,
    ?string $reference = null,
    ?array $metadata = null,
    ?Model $redeemedBy = null,
    ?string $notes = null
): void
```

Throws `ManualRedemptionNotAllowedException` if not allowed.

---

#### redeem

Redeem a voucher after successful order completion (checkout path).

```php
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;

app(VoucherServiceInterface::class)->redeem(
    code: $code,
    orderId: (string) $order->id,
    discountAmount: $allocatedAmount, // omit to recompute from the order subtotal
    currency: $order->currency,
);
```

When the allocated checkout amount is omitted for a percentage voucher, the
discount is recomputed from the order subtotal (honoring the voucher's
maximum-discount cap) instead of recording zero. Fixed vouchers fall back to
their face value.

---

#### getUsageHistory

Get usage history for a voucher.

```php
Voucher::getUsageHistory(string $code): Collection
```

Returns `Collection<VoucherUsage>`.

---

### Wallet Operations

#### addToWallet

Add a voucher to a user's wallet.

```php
Voucher::addToWallet(
    string $code,
    Model $owner,
    ?array $metadata = null
): VoucherWallet
```

---

#### removeFromWallet

Remove a voucher from a user's wallet.

```php
Voucher::removeFromWallet(string $code, Model $owner): bool
```

Returns `false` if voucher is already redeemed.

---

## Cart Methods

Methods available on Cart when using `InteractsWithVouchers`:

```php
use AIArmada\Cart\Facades\Cart;
```

### applyVoucher

```php
Cart::applyVoucher(string $code, int $order = 100): self
```

### removeVoucher

```php
Cart::removeVoucher(string $code): self
```

### clearVouchers

```php
Cart::clearVouchers(): self
```

### hasVoucher

```php
Cart::hasVoucher(?string $code = null): bool
```

### getVoucherCondition

```php
Cart::getVoucherCondition(string $code): ?VoucherCondition
```

### getAppliedVouchers

```php
Cart::getAppliedVouchers(): array<VoucherCondition>
```

### getAppliedVoucherCodes

```php
Cart::getAppliedVoucherCodes(): array<string>
```

### getVoucherDiscount

```php
Cart::getVoucherDiscount(): float
```

Returns the total discount in integer minor units (cents) of the cart currency. Format it
with `AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor()`.

### canAddVoucher

```php
Cart::canAddVoucher(): bool
```

### validateAppliedVouchers

```php
Cart::validateAppliedVouchers(): array<string>
```

---

## Data Objects

### VoucherData

```php
class VoucherData
{
    public string $id;
    public string $code;
    public string $name;
    public ?string $description;
    public VoucherType $type;
    public int $value;
    public ?array $valueConfig;
    public ?string $creditDestination;
    public int $creditDelayHours;
    public string $currency;
    public ?int $minCartValue;
    public ?int $maxDiscount;
    public ?int $usageLimit;
    public ?int $usageLimitPerUser;
    public bool $allowsManualRedemption;
    public int|string|null $ownerId;
    public ?string $ownerType;
    public ?DateTimeInterface $startsAt;
    public ?DateTimeInterface $expiresAt;
    public States\VoucherStatus $status;
    public ?array $targetDefinition;
    public ?array $metadata;
}
```

**Important:** All monetary values must be integers:
- `value`: cents for fixed amounts, basis points for percentages (e.g., 1000 = 10%)
- `minCartValue`: cents (e.g., 5000 = $50.00)
- `maxDiscount`: cents (e.g., 10000 = $100.00)

### VoucherValidationResult

```php
class VoucherValidationResult
{
    public bool $isValid;
    public ?string $reason;
    public ?array $details;
}
```

---

## Enums

### VoucherType

```php
enum VoucherType: string
{
    // Simple Types
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';

    // Compound Types (require value_config)
    case BuyXGetY = 'buy_x_get_y';
    case Tiered = 'tiered';
    case Bundle = 'bundle';
    case Cashback = 'cashback';
}
```

### VoucherStatus

Two distinct types share this name:

- `AIArmada\Vouchers\Enums\VoucherStatus` — the plain backed enum used for labels, filters,
  and option lists.
- `AIArmada\Vouchers\States\VoucherStatus` — the abstract Spatie model-state that the `status`
  cast on `Voucher` and `VoucherData::$status` actually resolves to. This is the type you
  pass to `create()` and receive from reads.

```php
// AIArmada\Vouchers\Enums\VoucherStatus
enum VoucherStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Expired = 'expired';
    case Depleted = 'depleted';
}
```

---

## Exceptions

| Exception | Description |
|-----------|-------------|
| `VoucherException` | Base exception class |
| `VoucherNotFoundException` | Voucher code not found |
| `VoucherExpiredException` | Voucher has expired |
| `InvalidVoucherException` | Voucher is invalid |
| `InvalidVoucherDataException` | Invalid data passed to VoucherData (e.g., float instead of integer) |
| `VoucherUsageLimitException` | Usage limit exceeded |
| `VoucherValidationException` | Voucher validation failed during checkout |
| `VoucherStackingException` | Stacking policy violation |
| `ManualRedemptionNotAllowedException` | Manual redemption not allowed |

---

## Events

### VoucherApplied

Fired when a voucher is applied to cart.

```php
class VoucherApplied
{
    public Cart $cart;
    public VoucherData $voucher;
}
```

### VoucherRemoved

Fired when a voucher is removed from cart.

```php
class VoucherRemoved
{
    public Cart $cart;
    public VoucherData $voucher;
}
```

---

## Contracts

### OwnerResolverInterface

The vouchers package uses the global owner resolver contract from `commerce-support`.

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

interface OwnerResolverInterface
{
    public function resolve(): ?Model;
}
```
