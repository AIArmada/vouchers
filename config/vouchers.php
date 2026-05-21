<?php

declare(strict_types=1);

$tablePrefix = env('VOUCHERS_TABLE_PREFIX', env('COMMERCE_TABLE_PREFIX', ''));

$tables = [
    'vouchers' => $tablePrefix . 'vouchers',
    'voucher_usage' => $tablePrefix . 'voucher_usage',
    'voucher_wallets' => $tablePrefix . 'voucher_wallets',
    'voucher_assignments' => $tablePrefix . 'voucher_assignments',
    'voucher_transactions' => $tablePrefix . 'voucher_transactions',
];

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('VOUCHERS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => $tables,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'default_currency' => 'MYR',

    'code' => [
        'prefix' => env('VOUCHERS_CODE_PREFIX', ''),
        'length' => (int) env('VOUCHERS_CODE_LENGTH', 8),
        'auto_uppercase' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Integration
    |--------------------------------------------------------------------------
    */
    'cart' => [
        'max_vouchers_per_cart' => (int) env('VOUCHERS_MAX_PER_CART', 1),
        'replace_when_max_reached' => true,
        'condition_order' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stacking Policies
    |--------------------------------------------------------------------------
    */
    'stacking' => [
        'mode' => env('VOUCHERS_STACKING_MODE', 'sequential'),

        'rules' => [
            [
                'type' => 'max_vouchers',
                'value' => (int) env('VOUCHERS_MAX_PER_CART', 1),
            ],
            [
                'type' => 'max_discount_percentage',
                'value' => 50,
            ],
            [
                'type' => 'type_restriction',
                'max_per_type' => [
                    'percentage' => 1,
                    'fixed' => 2,
                    'free_shipping' => 1,
                ],
            ],
        ],

        'auto_optimize' => false,
        'auto_replace' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'check_user_limit' => true,
        'check_global_limit' => true,
        'check_min_cart_value' => true,
        'check_targeting' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    */
    'tracking' => [
        'track_applications' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'enabled' => env('VOUCHERS_OWNER_ENABLED', false),
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redemption
    |--------------------------------------------------------------------------
    */
    'redemption' => [
        'manual_requires_flag' => true,
        'manual_channel' => 'manual',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reservation
    |--------------------------------------------------------------------------
    */
    'reservation' => [
        'ttl' => 900, // seconds (15 minutes)
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout Integration
    |--------------------------------------------------------------------------
    */
    'checkout' => [
        'block_on_invalid' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Affiliates Integration (aiarmada/affiliates)
    |--------------------------------------------------------------------------
    */
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
];
