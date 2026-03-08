# Creating Vouchers

## Basic Creation

Use the `Voucher` facade to create vouchers:

```php
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Enums\VoucherStatus;

$voucher = Voucher::create([
    'code' => 'SUMMER2024',
    'name' => 'Summer Sale 2024',
    'type' => VoucherType::Percentage,
    'value' => 2000, // 20.00%
    'currency' => 'MYR',
]);
```

## Voucher Types

### Percentage Discount

Value is stored as **basis points** (1/100 of a percent):

```php
Voucher::create([
    'code' => 'SAVE20',
    'name' => '20% Off',
    'type' => VoucherType::Percentage,
    'value' => 2000, // 20.00%
    'currency' => 'MYR',
]);

// For 10.50% discount:
'value' => 1050, // 10.50%
```

### Fixed Amount Discount

Value is stored as **cents**:

```php
Voucher::create([
    'code' => 'FLAT50',
    'name' => 'RM50 Off',
    'type' => VoucherType::Fixed,
    'value' => 5000, // RM50.00
    'currency' => 'MYR',
]);
```

### Free Shipping

```php
Voucher::create([
    'code' => 'FREESHIP',
    'name' => 'Free Shipping',
    'type' => VoucherType::FreeShipping,
    'value' => 0,
    'currency' => 'MYR',
]);
```

## Constraints

### Minimum Cart Value

Require a minimum cart value (stored as cents):

```php
Voucher::create([
    'code' => 'MIN100',
    'name' => '20% off orders over RM100',
    'type' => VoucherType::Percentage,
    'value' => 2000,
    'currency' => 'MYR',
    'min_cart_value' => 10000, // RM100.00 minimum
]);
```

### Maximum Discount

Cap the discount amount (stored as cents):

```php
Voucher::create([
    'code' => 'CAPPED',
    'name' => '50% off (max RM100)',
    'type' => VoucherType::Percentage,
    'value' => 5000, // 50%
    'currency' => 'MYR',
    'max_discount' => 10000, // Max RM100.00 discount
]);
```

## Usage Limits

### Global Limit

Limit total redemptions:

```php
Voucher::create([
    'code' => 'LIMITED',
    'name' => 'First 100 customers',
    'type' => VoucherType::Fixed,
    'value' => 2000,
    'currency' => 'MYR',
    'usage_limit' => 100,
]);
```

### Per-User Limit

Limit redemptions per user:

```php
Voucher::create([
    'code' => 'ONCE',
    'name' => 'One per customer',
    'type' => VoucherType::Percentage,
    'value' => 1500,
    'currency' => 'MYR',
    'usage_limit_per_user' => 1,
]);
```

## Time-Based Campaigns

### Start Date

Delay voucher availability:

```php
Voucher::create([
    'code' => 'NEWYEAR',
    'name' => 'New Year Sale',
    'type' => VoucherType::Percentage,
    'value' => 2500,
    'currency' => 'MYR',
    'starts_at' => Carbon::parse('2025-01-01 00:00:00'),
]);
```

### Expiry Date

Set voucher expiration:

```php
Voucher::create([
    'code' => 'FLASH',
    'name' => '24-Hour Flash Sale',
    'type' => VoucherType::Percentage,
    'value' => 3000,
    'currency' => 'MYR',
    'expires_at' => now()->addDay(),
]);
```

### Date Range

Combine start and expiry:

```php
Voucher::create([
    'code' => 'WEEKEND',
    'name' => 'Weekend Special',
    'type' => VoucherType::Fixed,
    'value' => 1000,
    'currency' => 'MYR',
    'starts_at' => Carbon::parse('2024-12-07 00:00:00'),
    'expires_at' => Carbon::parse('2024-12-08 23:59:59'),
]);
```

## Manual Redemption

Allow vouchers to be redeemed outside the cart flow:

```php
Voucher::create([
    'code' => 'GIFTCARD',
    'name' => 'Gift Card',
    'type' => VoucherType::Fixed,
    'value' => 5000,
    'currency' => 'MYR',
    'allows_manual_redemption' => true,
]);
```

## Voucher Status

Set initial status:

```php
Voucher::create([
    'code' => 'DRAFT',
    'name' => 'Draft Voucher',
    'type' => VoucherType::Percentage,
    'value' => 1000,
    'currency' => 'MYR',
    'status' => VoucherStatus::Paused, // Not yet active
]);
```

## Metadata

Store additional data:

```php
Voucher::create([
    'code' => 'PARTNER',
    'name' => 'Partner Discount',
    'type' => VoucherType::Percentage,
    'value' => 1500,
    'currency' => 'MYR',
    'metadata' => [
        'partner_id' => 'ABC123',
        'campaign' => 'holiday-2024',
        'source' => 'email',
    ],
]);
```

## Target Definition

Define voucher targeting rules:

```php
Voucher::create([
    'code' => 'CATEGORY',
    'name' => 'Electronics Discount',
    'type' => VoucherType::Percentage,
    'value' => 1000,
    'currency' => 'MYR',
    'target_definition' => [
        'type' => 'category',
        'categories' => ['electronics', 'computers'],
    ],
]);
```

## Updating Vouchers

```php
$voucher = Voucher::update('SUMMER2024', [
    'name' => 'Updated Summer Sale',
    'value' => 2500, // Increase to 25%
    'expires_at' => now()->addMonths(6),
]);
```

## Deleting Vouchers

```php
$deleted = Voucher::delete('SUMMER2024'); // Returns true/false
```

## Complete Example

```php
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Enums\VoucherType;

$voucher = Voucher::create([
    // Required
    'code' => 'MEGA2024',
    'name' => 'Mega Sale 2024',
    'type' => VoucherType::Percentage,
    'value' => 2000,
    'currency' => 'MYR',
    
    // Description
    'description' => 'Get 20% off on orders over RM200. Maximum discount RM100.',
    
    // Constraints
    'min_cart_value' => 20000,
    'max_discount' => 10000,
    
    // Usage limits
    'usage_limit' => 500,
    'usage_limit_per_user' => 2,
    
    // Time-based
    'starts_at' => now(),
    'expires_at' => now()->addMonths(3),
    
    // Manual redemption
    'allows_manual_redemption' => false,
    
    // Metadata
    'metadata' => [
        'campaign' => 'mega-sale-q1',
        'department' => 'marketing',
    ],
]);
```
