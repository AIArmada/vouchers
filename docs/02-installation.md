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

| Variable | Default | Description |
|----------|---------|-------------|
| `VOUCHERS_TABLE_PREFIX` | `''` | Package-specific table prefix (falls back to `COMMERCE_TABLE_PREFIX`) |
| `VOUCHERS_JSON_COLUMN_TYPE` | `jsonb` | JSON column type for migrations |
| `VOUCHERS_CODE_PREFIX` | `''` | Prefix for generated voucher codes |
| `VOUCHERS_CODE_LENGTH` | `8` | Length of generated voucher codes |
| `VOUCHERS_STACKING_MODE` | `sequential` | Stacking application mode |
| `VOUCHERS_MAX_PER_CART` | `1` | Maximum vouchers per cart |
| `VOUCHERS_OWNER_ENABLED` | `false` | Enable multi-tenancy |
| `VOUCHERS_AFFILIATES_ENABLED` | `false` | Enable affiliates integration |
| `COMMERCE_OWNER_RESOLVER` | `AIArmada\CommerceSupport\Support\NullOwnerResolver` | Global owner resolver used when multi-tenancy is enabled |

Validation checks, application tracking, owner include-global/auto-assign, and manual-redemption flags are hardcoded `true`/`false` defaults in `config/vouchers.php`; change them in the config file, not via environment.

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
