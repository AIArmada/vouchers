---
title: Installation
---

# Installation

## Requirements

- PHP 8.2 or higher
- Laravel 12 or higher
- AIArmada Cart package

## Installation via Composer

```bash
composer require aiarmada/vouchers
```

The package will auto-register its service provider and facade.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=vouchers-config
```

This creates `config/vouchers.php` with all available options.

## Database Migrations

Publish and run migrations:

```bash
php artisan vendor:publish --tag=vouchers-migrations
php artisan migrate
```

This creates the following tables:

| Table | Purpose |
|-------|---------|
| `vouchers` | Stores voucher definitions |
| `voucher_usage` | Tracks each voucher redemption |
| `voucher_wallets` | User wallet entries for saved vouchers |
| `voucher_assignments` | Voucher assignments to users (credit system) |
| `voucher_transactions` | Transaction history for credit-based vouchers |

## JSON Column Type (PostgreSQL)

For PostgreSQL users who want JSONB columns with GIN indexes, set the environment variable **before** running migrations:

```env
# Global setting for all commerce packages
COMMERCE_JSON_COLUMN_TYPE=jsonb

# Or package-specific override
VOUCHERS_JSON_COLUMN_TYPE=jsonb
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `VOUCHERS_AUTO_UPPERCASE` | `true` | Uppercase codes for case-insensitive matching |
| `VOUCHERS_MAX_PER_CART` | `1` | Maximum vouchers per cart (0=disabled, -1=unlimited) |
| `VOUCHERS_REPLACE_WHEN_MAX_REACHED` | `true` | Replace oldest voucher when max reached |
| `VOUCHERS_CONDITION_ORDER` | `50` | Order in cart calculation chain |
| `VOUCHERS_ALLOW_STACKING` | `false` | Allow multiple vouchers to stack |
| `VOUCHERS_CHECK_USER_LIMIT` | `true` | Check per-user usage limits |
| `VOUCHERS_CHECK_GLOBAL_LIMIT` | `true` | Check global usage limits |
| `VOUCHERS_CHECK_MIN_CART_VALUE` | `true` | Check minimum cart value |
| `VOUCHERS_TRACK_APPLICATIONS` | `true` | Track applied_count for analytics |
| `VOUCHERS_OWNER_ENABLED` | `false` | Enable multi-tenancy |
| `COMMERCE_OWNER_RESOLVER` | `AIArmada\CommerceSupport\Contracts\NullOwnerResolver` | Global owner resolver used when multi-tenancy is enabled |
| `VOUCHERS_OWNER_INCLUDE_GLOBAL` | `false` | Include global vouchers in scoped queries |
| `VOUCHERS_OWNER_AUTO_ASSIGN_ON_CREATE` | `true` | Auto-assign vouchers to current owner |
| `VOUCHERS_MANUAL_REQUIRES_FLAG` | `true` | Require flag for manual redemption |

## Verification

Verify installation by creating a test voucher:

```php
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Enums\VoucherType;

$voucher = Voucher::create([
    'code' => 'TEST10',
    'name' => 'Test Voucher',
    'type' => VoucherType::Percentage,
    'value' => 1000, // 10%
    'currency' => 'MYR',
]);

// Should return the voucher data
dd(Voucher::find('TEST10'));
```
