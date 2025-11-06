<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Voucher System Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how vouchers behave in your application.
    |
    */

    'enabled' => env('VOUCHERS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Voucher Code Settings
    |--------------------------------------------------------------------------
    */

    'code' => [
        'case_sensitive' => env('VOUCHERS_CASE_SENSITIVE', false),
        'auto_uppercase' => env('VOUCHERS_AUTO_UPPERCASE', true),
        'min_length' => env('VOUCHERS_MIN_LENGTH', 4),
        'max_length' => env('VOUCHERS_MAX_LENGTH', 32),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Integration
    |--------------------------------------------------------------------------
    */

    'cart' => [
        'max_vouchers_per_cart' => env('VOUCHERS_MAX_PER_CART', 1),
        'replace_when_max_reached' => env('VOUCHERS_REPLACE_WHEN_MAX_REACHED', true),
        'auto_apply_best' => env('VOUCHERS_AUTO_APPLY_BEST', false),
        'condition_order' => env('VOUCHERS_CONDITION_ORDER', 50),
        'allow_stacking' => env('VOUCHERS_ALLOW_STACKING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    'validation' => [
        'check_user_limit' => env('VOUCHERS_CHECK_USER_LIMIT', true),
        'check_global_limit' => env('VOUCHERS_CHECK_GLOBAL_LIMIT', true),
        'check_date_range' => env('VOUCHERS_CHECK_DATE_RANGE', true),
        'check_min_cart_value' => env('VOUCHERS_CHECK_MIN_CART_VALUE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage Tracking
    |--------------------------------------------------------------------------
    */

    'tracking' => [
        'enabled' => env('VOUCHERS_TRACKING_ENABLED', true),
        'store_cart_snapshot' => env('VOUCHERS_STORE_CART_SNAPSHOT', true),
        'cleanup_after_days' => env('VOUCHERS_CLEANUP_AFTER_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */

    'events' => [
        'dispatch' => env('VOUCHERS_DISPATCH_EVENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */

    'table_names' => [
        'vouchers' => 'vouchers',
        'voucher_usage' => 'voucher_usage',
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
    | Voucher Ownership
    |--------------------------------------------------------------------------
    |
    | Configure how vouchers are associated with a specific owner / tenant.
    | When disabled, all vouchers are treated as global. When enabled, the
    | resolver should return the current owner model so lookups can be scoped.
    |
    */

    'owner' => [
        'enabled' => env('VOUCHERS_OWNER_ENABLED', false),
        'resolver' => AIArmada\Vouchers\Support\Resolvers\NullOwnerResolver::class,
        'include_global' => env('VOUCHERS_OWNER_INCLUDE_GLOBAL', true),
        'auto_assign_on_create' => env('VOUCHERS_OWNER_AUTO_ASSIGN_ON_CREATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redemption Channels
    |--------------------------------------------------------------------------
    |
    | Configure manual redemption policies and channel names that are used
    | when tracking voucher usage. Manual redemption can be toggled per
    | voucher by setting the allows_manual_redemption flag.
    |
    */

    'redemption' => [
        'manual_requires_flag' => env('VOUCHERS_MANUAL_REQUIRES_FLAG', true),
        'channels' => [
            'automatic' => 'automatic',
            'manual' => 'manual',
            'api' => 'api',
        ],
    ],
];
