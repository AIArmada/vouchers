---
title: Configuration
---

# Configuration

Configuration lives in `config/vouchers.php`.

## Database

```php
'database' => [
    'table_prefix' => env('VOUCHERS_TABLE_PREFIX', env('COMMERCE_TABLE_PREFIX', '')),
    'json_column_type' => env('VOUCHERS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    'tables' => [
        'vouchers' => 'vouchers',
        'voucher_usage' => 'voucher_usage',
        'voucher_wallets' => 'voucher_wallets',
        'voucher_assignments' => 'voucher_assignments',
        'voucher_transactions' => 'voucher_transactions',
    ],
],
```

Use `VOUCHERS_TABLE_PREFIX` when you need a package-specific prefix. JSON column type follows the shared `COMMERCE_JSON_COLUMN_TYPE` fallback.

## Defaults

```php
'default_currency' => 'MYR',

'code' => [
    'prefix' => env('VOUCHERS_CODE_PREFIX', ''),
    'length' => (int) env('VOUCHERS_CODE_LENGTH', 8),
    'auto_uppercase' => true,
],
```

- `default_currency` is the opinionated package default for money-like voucher fields.
- `code.auto_uppercase` is always enabled, so code matching stays case-insensitive.

## Cart Integration

```php
'cart' => [
    'max_vouchers_per_cart' => (int) env('VOUCHERS_MAX_PER_CART', 1),
    'replace_when_max_reached' => true,
    'condition_order' => 50,
],
```

These keys control how many vouchers can be attached to a cart and where voucher conditions run inside the cart calculation chain.

## Stacking Policies

```php
'stacking' => [
    'mode' => env('VOUCHERS_STACKING_MODE', 'sequential'),
    'rules' => [
        ['type' => 'max_vouchers', 'value' => (int) env('VOUCHERS_MAX_PER_CART', 1)],
        ['type' => 'max_discount_percentage', 'value' => 50],
        ['type' => 'type_restriction', 'max_per_type' => [
            'percentage' => 1,
            'fixed' => 2,
            'free_shipping' => 1,
        ]],
    ],
    'auto_optimize' => false,
    'auto_replace' => true,
],
```

`stacking.mode` is the main operator-facing switch. The package also ships default guardrails for count, max discount percentage, and per-type restrictions.

## Validation

```php
'validation' => [
    'check_user_limit' => true,
    'check_global_limit' => true,
    'check_min_cart_value' => true,
    'check_targeting' => true,
],
```

These checks are on by default. If you relax them, do it deliberately and document the business reason because eligibility behavior changes immediately.

## Tracking

```php
'tracking' => [
    'track_applications' => true,
],
```

Application tracking increments voucher analytics when a code is applied, even before redemption completes.

## Owner Scoping

```php
'owner' => [
    'enabled' => env('VOUCHERS_OWNER_ENABLED', false),
    'include_global' => false,
    'auto_assign_on_create' => true,
],
```

- `enabled` is the only environment-controlled owner toggle.
- `include_global` is intentionally `false` by default, so owner queries do not silently mix in global vouchers.
- `auto_assign_on_create` keeps new vouchers inside the current owner boundary when owner mode is on.

## Redemption

```php
'redemption' => [
    'manual_requires_flag' => true,
    'manual_channel' => 'manual',
],
```

Manual redemption is opt-in by default. The package expects manual flows to stay explicit and privileged.

## Reservation

```php
'reservation' => [
    'ttl' => 900,
],
```

Reservation TTL is stored in seconds and defaults to 15 minutes.

## Checkout Integration

```php
'checkout' => [
    'block_on_invalid' => false,
],
```

This controls whether checkout should stop immediately when a voucher becomes invalid during checkout orchestration.

## Affiliates Integration

```php
'affiliates' => [
    'enabled' => env('VOUCHERS_AFFILIATES_ENABLED', false),
    'auto_create_voucher' => false,
    'create_on_activation' => true,
    'set_default_voucher_code' => true,
    'code_format' => 'prefix_code',
    'code_prefix' => 'REF',
    'voucher_defaults' => [
        'type' => 'percentage',
        'value' => 1000,
        'currency' => null,
        'status' => 'active',
    ],
],
```

This block is only relevant when `aiarmada/affiliates` is installed. It controls how affiliate-linked vouchers are seeded and named.