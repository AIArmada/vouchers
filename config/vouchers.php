<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Voucher Code Settings
    |--------------------------------------------------------------------------
    */

    'code' => [
        // Automatically convert voucher codes to uppercase for case-insensitive matching
        'auto_uppercase' => env('VOUCHERS_AUTO_UPPERCASE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Integration
    |--------------------------------------------------------------------------
    */

    'cart' => [
        // Maximum number of vouchers that can be applied to a cart (0 = disabled, -1 = unlimited)
        'max_vouchers_per_cart' => env('VOUCHERS_MAX_PER_CART', 1),

        // When max vouchers reached, replace the oldest voucher with the new one
        'replace_when_max_reached' => env('VOUCHERS_REPLACE_WHEN_MAX_REACHED', true),

        // Order of voucher condition application (lower = earlier in calculation chain)
        // Example: Tax (100), Shipping (75), Voucher (50), Fee (25)
        // Vouchers with order=50 apply after fees but before shipping/tax
        // This affects how discounts interact with other cart conditions
        'condition_order' => env('VOUCHERS_CONDITION_ORDER', 50),

        // Allow multiple vouchers to stack (apply sequentially)
        'allow_stacking' => env('VOUCHERS_ALLOW_STACKING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    |
    | Control which validation checks are performed when applying vouchers.
    | Note: Date range (starts_at/expires_at) and status checks are always enforced.
    |
    */

    'validation' => [
        // Check per-user usage limits (usage_limit_per_user)
        'check_user_limit' => env('VOUCHERS_CHECK_USER_LIMIT', true),

        // Check global usage limits (usage_limit)
        'check_global_limit' => env('VOUCHERS_CHECK_GLOBAL_LIMIT', true),

        // Check minimum cart value requirements (min_cart_value)
        'check_min_cart_value' => env('VOUCHERS_CHECK_MIN_CART_VALUE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Tracking
    |--------------------------------------------------------------------------
    |
    | Track how many times vouchers are applied to carts (not just redeemed).
    | Useful for campaign monitoring and conversion rate analysis.
    |
    */

    'tracking' => [
        // Track applied_count (incremented when voucher is added to cart)
        'track_applications' => env('VOUCHERS_TRACK_APPLICATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */

    'table_names' => [
        'vouchers' => 'vouchers',
        'voucher_usage' => 'voucher_usage',
        'voucher_wallets' => 'voucher_wallets',
        'voucher_assignments' => 'voucher_assignments',
        'voucher_transactions' => 'voucher_transactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Options
    |--------------------------------------------------------------------------
    |
    | Migrations default to portable JSON columns. If you prefer JSONB on
    | PostgreSQL, set this to true BEFORE running initial migrations. When
    | enabled and using pgsql, migrations will convert JSON columns to JSONB
    | and create GIN indexes.
    |
    */

    'database' => [
        // Accepts 'json' or 'jsonb' (pgsql only). Defaults to global COMMERCE_JSON_COLUMN_TYPE.
        'json_column_type' => env('VOUCHERS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voucher Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | Enable multi-tenancy to scope vouchers to specific owners/tenants.
    |
    | When DISABLED (false):
    | - All vouchers are global and visible to everyone
    | - Single-tenant applications use this setting
    | - Example: E-commerce store with one business owner
    |
    | When ENABLED (true):
    | - Vouchers belong to specific owners (e.g., Store, Merchant, Vendor)
    | - Each owner only sees/manages their own vouchers
    | - Requires a custom VoucherOwnerResolver to identify current owner
    | - Example: Multi-vendor marketplace where each vendor creates vouchers
    |
    | The 'resolver' class determines WHO the current owner is (returns Model)
    | The 'include_global' setting allows global vouchers alongside owned ones
    | The 'auto_assign_on_create' auto-assigns vouchers to current owner
    |
    */

    'owner' => [
        // Enable owner-based voucher scoping (multi-tenancy)
        'enabled' => env('VOUCHERS_OWNER_ENABLED', false),

        // Class that resolves the current owner (must implement VoucherOwnerResolver)
        'resolver' => AIArmada\Vouchers\Support\Resolvers\NullOwnerResolver::class,

        // Allow owners to see global vouchers (owner_id = null) alongside their own
        'include_global' => env('VOUCHERS_OWNER_INCLUDE_GLOBAL', true),

        // Automatically assign vouchers to current owner when created
        'auto_assign_on_create' => env('VOUCHERS_OWNER_AUTO_ASSIGN_ON_CREATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redemption Settings
    |--------------------------------------------------------------------------
    |
    | Manual redemption allows vouchers to be redeemed OUTSIDE the cart flow.
    | Use cases: In-store purchases, phone orders, admin-initiated redemptions
    |
    | Normal flow: User adds voucher to cart → completes checkout → redeemed
    | Manual flow: Call VoucherService::redeemManually() → redeemed immediately
    |
    | The 'manual_requires_flag' setting adds a safety layer:
    |
    | When TRUE (recommended):
    | - Voucher must have allows_manual_redemption=true in database
    | - Prevents accidental manual redemption of cart-only vouchers
    | - Example: Gift cards OK for manual, but "first purchase" vouchers are not
    |
    | When FALSE:
    | - ANY voucher can be manually redeemed (less safe)
    | - Useful if you want all vouchers available for manual redemption
    |
    */

    'redemption' => [
        // Require allows_manual_redemption=true flag on voucher for manual redemption
        // Set to false to allow manual redemption of any voucher
        'manual_requires_flag' => env('VOUCHERS_MANUAL_REQUIRES_FLAG', true),

        // Channel name used for manual redemptions in voucher_usage table
        'manual_channel' => 'manual',
    ],
];
