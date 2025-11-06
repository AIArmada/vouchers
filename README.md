# AIArmada Cart Vouchers

> Professional voucher and coupon system for AIArmada Cart

Add powerful voucher functionality to your Laravel shopping cart with support for percentage discounts, fixed amounts, free shipping, usage limits, and advanced validation rules.

## ✨ Features

- 🎫 **Multiple Voucher Types** - Percentage, fixed amount, free shipping
- 🔒 **Usage Limits** - Global limits and per-user restrictions
- 📅 **Time-Based** - Start and expiry dates for campaigns
- 🎯 **Targeted Discounts** - Apply to specific products or categories
- 💰 **Smart Constraints** - Minimum cart values, maximum discounts
- 🧑‍🤝‍🧑 **Multi-Owner Aware** - Scope vouchers to the current tenant or merchant using a configurable resolver
- 🧾 **Manual Redemption** - Record offline usage with channels, metadata, and staff attribution
- 📊 **Usage Tracking** - Complete history of voucher applications
- ⚡ **Real-Time Validation** - Instant feedback on voucher validity
- 🔐 **Secure** - Built-in validation and fraud prevention

## 📦 Installation

```bash
composer require aiarmada/cart-vouchers
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=vouchers-config
php artisan vendor:publish --tag=vouchers-migrations
```

Run migrations:

```bash
php artisan migrate
```

### JSON vs JSONB (PostgreSQL)

Migrations default to portable `json` columns. To opt into `jsonb` on a fresh install, set one of the following BEFORE running migrations:

```env
COMMERCE_JSON_COLUMN_TYPE=jsonb
# or per-package override
VOUCHERS_JSON_COLUMN_TYPE=jsonb
```

Or run the interactive setup:

```bash
php artisan commerce:configure-database
```

When using PostgreSQL + `jsonb`, GIN indexes are created automatically on voucher JSON fields: `applicable_products`, `excluded_products`, `applicable_categories`, and `metadata`.

## 🚀 Quick Start

### Create a Voucher

```php
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Enums\VoucherType;

$voucher = Voucher::create([
    'code' => 'SUMMER2024',
    'name' => 'Summer Sale 2024',
    'description' => '20% off your entire order',
    'type' => VoucherType::Percentage,
    'value' => 20.00,
    'min_cart_value' => 50.00,
    'usage_limit' => 1000,
    'usage_limit_per_user' => 1,
    'starts_at' => now(),
    'expires_at' => now()->addMonths(3),
]);
```

### Apply to Cart

```php
use AIArmada\Cart\Facades\Cart;

try {
    Cart::applyVoucher('SUMMER2024');
    
    $total = Cart::getTotal();
    echo "Total with voucher: {$total->format()}";
    
} catch (\AIArmada\Vouchers\Exceptions\VoucherNotFoundException $e) {
    // Voucher doesn't exist
} catch (\AIArmada\Vouchers\Exceptions\VoucherExpiredException $e) {
    // Voucher has expired
}
```

### Manual Redemption

Record offline usage for POS or concierge scenarios while keeping analytics accurate:

```php
use AIArmada\Vouchers\Facades\Voucher;
use Akaunting\Money\Money;

Voucher::redeemManually(
    code: 'SUMMER2024',
    userIdentifier: 'order-1001',
    discountAmount: Money::USD(2500),
    reference: 'counter-19',
    metadata: ['source' => 'retail-pos'],
    notes: 'Awarded during in-store event'
);
```

### Owner Scoping

Enable multi-tenant or multi-merchant scoping by registering a resolver that returns the current owner model:

```php
// config/vouchers.php
'owner' => [
    'enabled' => true,
    'resolver' => App\Support\Vouchers\CurrentOwnerResolver::class,
],
```

When enabled, all lookups automatically constrain vouchers to the resolved owner while optionally including global vouchers. New vouchers created through the service are associated with the current owner for you.

## 📚 Documentation

- [Installation & Setup](docs/installation.md)
- [Creating Vouchers](docs/creating-vouchers.md)
- [Validation Rules](docs/validation-rules.md)
- [Cart Integration](docs/cart-integration.md)
- [Usage Tracking](docs/usage-tracking.md)
- [API Reference](docs/api-reference.md)

## 🧪 Testing

```bash
composer test
```

## 📝 Requirements

- PHP 8.2+
- Laravel 12+
- AIArmada Cart 2.0+

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](../../CONTRIBUTING.md) for details.

## 🔗 Related Packages

- [aiarmada/cart](https://github.com/aiarmada/cart) - Core shopping cart (required)
- [aiarmada/filament-cart](https://github.com/aiarmada/filament-cart) - Filament admin panel for cart

## 💬 Support

- [Documentation](https://github.com/aiarmada/cart/tree/main/docs)
- [Issues](https://github.com/aiarmada/cart/issues)
- [Discussions](https://github.com/aiarmada/cart/discussions)
