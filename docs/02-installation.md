---
title: Installation
---

# Installation

## Requirements

- PHP 8.4 or higher
- Laravel 13 or higher
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

## JSON Column Type (PostgreSQL)

For PostgreSQL users who want JSONB columns with GIN indexes, set the environment variable **before** running migrations:

```env
# Global setting for all commerce packages
COMMERCE_JSON_COLUMN_TYPE=jsonb

# Or package-specific override
VOUCHERS_JSON_COLUMN_TYPE=jsonb
```

## Environment Variables

`config/vouchers.php` reads only these environment variables. Every other key in the
config is a literal default and is changed in `config/vouchers.php` instead.

| Variable | Default | Description |
|----------|---------|-------------|
| `VOUCHERS_TABLE_PREFIX` | `COMMERCE_TABLE_PREFIX` or `''` | Prefix for the three voucher tables |
| `VOUCHERS_JSON_COLUMN_TYPE` | `jsonb` | JSON column type for voucher JSON columns |
| `VOUCHERS_CODE_PREFIX` | `''` | Prefix for auto-generated voucher codes |
| `VOUCHERS_CODE_LENGTH` | `8` | Random length of auto-generated codes |
| `VOUCHERS_STACKING_MODE` | `sequential` | Stacking strategy |
| `VOUCHERS_MAX_PER_CART` | `1` | Value of the `max_vouchers` stacking rule (0 = disabled, -1 = unlimited) |
| `VOUCHERS_OWNER_ENABLED` | `false` | Enable owner scoping |
| `VOUCHERS_AFFILIATES_ENABLED` | `false` | Enable the `aiarmada/affiliates` integration |

Global commerce variables that also apply:

| Variable | Default | Description |
|----------|---------|-------------|
| `COMMERCE_TABLE_PREFIX` | `''` | Fallback table prefix when `VOUCHERS_TABLE_PREFIX` is unset |
| `COMMERCE_JSON_COLUMN_TYPE` | unset | Overrides the JSON column type for every commerce package |
| `COMMERCE_OWNER_RESOLVER` | `AIArmada\CommerceSupport\Support\NullOwnerResolver` | Owner resolver used when owner mode is enabled |

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
