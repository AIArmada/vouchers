# AIArmada Vouchers

A voucher and coupon system for Laravel built on the [AIArmada Cart](../cart) package's condition system. Provides percentage discounts, fixed amounts, free shipping, multi-tenancy support, and comprehensive usage tracking.

> **Architectural Note**: This package is a first-party extension of the cart package. Vouchers are converted to cart conditions via the `VoucherCondition` adapter, leveraging the cart's pricing pipeline for discount calculation.

## Features

- **Multiple Voucher Types** — Percentage discounts, fixed amounts, and free shipping
- **Cart Condition Integration** — Vouchers become cart conditions via `CartConditionConvertible`
- **Dynamic Validation** — Real-time eligibility checks through cart's rules factory system
- **Usage Limits** — Global limits and per-user restrictions
- **Time-Based Campaigns** — Start and expiry dates for promotions
- **Voucher Wallet** — Users can save vouchers for later use
- **Multi-Tenancy** — Scope vouchers to owners/merchants via configurable resolver
- **Manual Redemption** — Record offline usage with channels, metadata, and attribution
- **Usage Tracking** — Complete history and conversion analytics

## Requirements

- PHP 8.2+
- Laravel 12+
- **AIArmada Cart** (required dependency)

## Installation

```bash
composer require aiarmada/vouchers
```

> **Note**: The cart package is automatically installed as a required dependency.

Publish configuration and migrations:

```bash
php artisan vendor:publish --tag=vouchers-config
php artisan vendor:publish --tag=vouchers-migrations
php artisan migrate
```

## Quick Start

### Creating Vouchers

```php
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Enums\VoucherType;

$voucher = Voucher::create([
    'code' => 'SUMMER2024',
    'name' => 'Summer Sale 2024',
    'description' => '20% off your entire order',
    'type' => VoucherType::Percentage,
    'value' => 2000, // 20.00% (stored as basis points)
    'currency' => 'MYR',
    'min_cart_value' => 5000, // RM50.00 minimum (stored as cents)
    'max_discount' => 10000, // RM100.00 max discount (stored as cents)
    'usage_limit' => 1000,
    'usage_limit_per_user' => 1,
    'starts_at' => now(),
    'expires_at' => now()->addMonths(3),
]);
```

### Applying to Cart

```php
use AIArmada\Cart\Facades\Cart;

try {
    Cart::applyVoucher('SUMMER2024');
    echo "Total: " . Cart::getTotal()->format();
} catch (\AIArmada\Vouchers\Exceptions\InvalidVoucherException $e) {
    echo $e->getMessage();
}
```

### Checking and Removing Vouchers

```php
// Check if voucher is applied
if (Cart::hasVoucher('SUMMER2024')) {
    // Get applied voucher codes
    $codes = Cart::getAppliedVoucherCodes();
    
    // Get total discount
    $discount = Cart::getVoucherDiscount();
    
    // Remove voucher
    Cart::removeVoucher('SUMMER2024');
}

// Clear all vouchers
Cart::clearVouchers();
```

## Voucher Types

| Type | Enum Value | Description |
|------|------------|-------------|
| Percentage | `VoucherType::Percentage` | Reduces cart total by percentage (stored as basis points: 1050 = 10.50%) |
| Fixed | `VoucherType::Fixed` | Reduces cart total by fixed amount (stored as cents) |
| Free Shipping | `VoucherType::FreeShipping` | Removes shipping costs |

## Voucher Status

| Status | Description |
|--------|-------------|
| `VoucherStatus::Active` | Voucher can be used |
| `VoucherStatus::Paused` | Temporarily disabled |
| `VoucherStatus::Expired` | Past expiry date |
| `VoucherStatus::Depleted` | Usage limit reached |

## Voucher Wallet

Allow users to save vouchers for later use:

```php
use AIArmada\Vouchers\Traits\HasVouchers;

class User extends Model
{
    use HasVouchers;
}
```

```php
// Add voucher to wallet
$user->addVoucherToWallet('SUMMER2024');

// Check if voucher exists
$user->hasVoucherInWallet('SUMMER2024');

// Get available vouchers
$available = $user->getAvailableVouchers();

// Get redeemed vouchers
$redeemed = $user->getRedeemedVouchers();

// Get expired vouchers
$expired = $user->getExpiredVouchers();

// Mark as redeemed
$user->markVoucherAsRedeemed('SUMMER2024');

// Remove from wallet (only if not redeemed)
$user->removeVoucherFromWallet('SUMMER2024');
```

## Manual Redemption

Record offline usage for POS or admin-initiated redemptions:

```php
use AIArmada\Vouchers\Facades\Voucher;
use Akaunting\Money\Money;

Voucher::redeemManually(
    code: 'SUMMER2024',
    discountAmount: Money::MYR(2500), // RM25.00
    reference: 'POS-001',
    metadata: ['terminal' => 'store-a'],
    redeemedBy: $staff,
    notes: 'In-store promotion'
);
```

By default, vouchers must have `allows_manual_redemption = true` to be redeemed manually. Configure this in `vouchers.redemption.manual_requires_flag`.

## Multi-Tenancy (Owner Scoping)

Scope vouchers to specific owners like merchants or stores:

```php
// config/vouchers.php
'owner' => [
    'enabled' => true,
    'include_global' => true, // Also show global vouchers
    'auto_assign_on_create' => true,
],
```

Bind the current owner resolver centrally via `commerce-support`:

```env
VOUCHERS_OWNER_ENABLED=true
COMMERCE_OWNER_RESOLVER=App\Support\CurrentMerchantResolver
```

Create a resolver implementing `OwnerResolverInterface`:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

class CurrentMerchantResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        return auth()->user()?->merchant;
    }
}
```

## Usage Analytics

Track voucher performance:

```php
$voucher = \AIArmada\Vouchers\Models\Voucher::find($id);

// Get conversion rate (redeemed vs applied)
$conversionRate = $voucher->getConversionRate(); // e.g., 45.5%

// Get abandoned count
$abandoned = $voucher->getAbandonedCount();

// Get comprehensive statistics
$stats = $voucher->getStatistics();
// [
//     'applied_count' => 100,
//     'redeemed_count' => 45,
//     'abandoned_count' => 55,
//     'conversion_rate' => 45.0,
//     'remaining_uses' => 955,
// ]
```

## Configuration

### Code Settings

```php
'code' => [
    // Automatically uppercase codes for case-insensitive matching
    'auto_uppercase' => env('VOUCHERS_AUTO_UPPERCASE', true),
],
```

### Cart Integration

```php
'cart' => [
    // Maximum vouchers per cart (0 = disabled, -1 = unlimited)
    'max_vouchers_per_cart' => env('VOUCHERS_MAX_PER_CART', 1),
    
    // Replace oldest voucher when max reached
    'replace_when_max_reached' => env('VOUCHERS_REPLACE_WHEN_MAX_REACHED', true),
    
    // Condition order in calculation chain (lower = earlier)
    'condition_order' => env('VOUCHERS_CONDITION_ORDER', 50),
    
    // Allow vouchers to stack sequentially
    'allow_stacking' => env('VOUCHERS_ALLOW_STACKING', false),
],
```

### Validation

```php
'validation' => [
    'check_user_limit' => env('VOUCHERS_CHECK_USER_LIMIT', true),
    'check_global_limit' => env('VOUCHERS_CHECK_GLOBAL_LIMIT', true),
    'check_min_cart_value' => env('VOUCHERS_CHECK_MIN_CART_VALUE', true),
],
```

### Application Tracking

```php
'tracking' => [
    // Track applied_count when voucher is added to cart
    'track_applications' => env('VOUCHERS_TRACK_APPLICATIONS', true),
],
```

### Database Tables

```php
'table_names' => [
    'vouchers' => 'vouchers',
    'voucher_usage' => 'voucher_usage',
    'voucher_wallets' => 'voucher_wallets',
    'voucher_assignments' => 'voucher_assignments',
    'voucher_transactions' => 'voucher_transactions',
],
```

### Manual Redemption

```php
'redemption' => [
    // Require allows_manual_redemption flag on voucher
    'manual_requires_flag' => env('VOUCHERS_MANUAL_REQUIRES_FLAG', true),
    
    // Channel name for manual redemptions
    'manual_channel' => 'manual',
],
```

## Facade Methods

```php
use AIArmada\Vouchers\Facades\Voucher;

// CRUD
Voucher::find(string $code): ?VoucherData
Voucher::findOrFail(string $code): VoucherData
Voucher::create(array $data): VoucherData
Voucher::update(string $code, array $data): VoucherData
Voucher::delete(string $code): bool

// Validation
Voucher::validate(string $code, mixed $cart): VoucherValidationResult
Voucher::isValid(string $code): bool
Voucher::canBeUsedBy(string $code, ?Model $user = null): bool
Voucher::getRemainingUses(string $code): int

// Usage
Voucher::recordUsage(string $code, Money $discountAmount, ...): void
Voucher::redeemManually(string $code, Money $discountAmount, ...): void
Voucher::getUsageHistory(string $code): Collection

// Wallet
Voucher::addToWallet(string $code, Model $owner, ?array $metadata = null): VoucherWallet
Voucher::removeFromWallet(string $code, Model $owner): bool
```

## Cart Methods

When using `InteractsWithVouchers` trait (included in Cart by default):

```php
use AIArmada\Cart\Facades\Cart;

Cart::applyVoucher(string $code, int $order = 100): self
Cart::removeVoucher(string $code): self
Cart::clearVouchers(): self
Cart::hasVoucher(?string $code = null): bool
Cart::getVoucherCondition(string $code): ?VoucherCondition
Cart::getAppliedVouchers(): array
Cart::getAppliedVoucherCodes(): array
Cart::getVoucherDiscount(): float
Cart::canAddVoucher(): bool
Cart::validateAppliedVouchers(): array
```

## Exceptions

| Exception | Description |
|-----------|-------------|
| `VoucherNotFoundException` | Voucher code does not exist |
| `VoucherExpiredException` | Voucher has expired |
| `InvalidVoucherException` | Voucher is invalid (inactive, not started, min cart value not met) |
| `InvalidVoucherDataException` | Invalid data passed to VoucherData (e.g., float instead of integer) |
| `VoucherUsageLimitException` | Usage limit exceeded |
| `VoucherValidationException` | Voucher validation failed during checkout |
| `VoucherStackingException` | Stacking policy violation |
| `ManualRedemptionNotAllowedException` | Manual redemption not allowed for this voucher |

## Events

| Event | Description |
|-------|-------------|
| `VoucherApplied` | Fired when a voucher is applied to cart |
| `VoucherRemoved` | Fired when a voucher is removed from cart |

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
