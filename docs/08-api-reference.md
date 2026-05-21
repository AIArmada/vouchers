---
title: API Reference
---

# API Reference

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
- `status` - VoucherStatus enum (default: Active)
- `metadata` - Additional data array
- `target_definition` - Targeting rules array

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
- `voucher` - VoucherData (when valid)

---

#### isValid

Check if a voucher is valid (without cart context).

```php
Voucher::isValid(string $code): bool
```

---

#### canBeUsedBy

Check if a user can use a voucher.

```php
Voucher::canBeUsedBy(string $code, ?Model $user = null): bool
```

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
    public VoucherStatus $status;
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
    public ?VoucherData $voucher;
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

```php
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
