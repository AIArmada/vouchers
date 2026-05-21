---
title: Multi-Tenancy
---

# Multi-Tenancy (Owner Scoping)

The vouchers package supports multi-tenancy, allowing vouchers to be scoped to specific owners such as merchants, stores, or vendors.

## Configuration

Enable owner scoping in `config/vouchers.php`:

```php
'owner' => [
    'enabled' => env('VOUCHERS_OWNER_ENABLED', false),
    'include_global' => false,
    'auto_assign_on_create' => true,
],
```

Add to your `.env`:

```env
VOUCHERS_OWNER_ENABLED=true
```

Bind the owner resolver in `AppServiceProvider::register()`:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

$this->app->bind(OwnerResolverInterface::class, CurrentTenantResolver::class);
```

### Options

| Option | Description |
|--------|-------------|
| `enabled` | Enable/disable owner scoping |
| `include_global` | Include global vouchers (owner_type and owner_id null) in queries |
| `auto_assign_on_create` | Automatically assign new vouchers to current owner |

## Creating a Resolver

Implement `OwnerResolverInterface`:

```php
namespace App\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

class CurrentMerchantResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        // Return the current owner model
        return auth()->user()?->merchant;
    }
}
```

### Example Resolvers

**Tenant-based (Spatie Multitenancy):**

```php
use Spatie\Multitenancy\Models\Tenant;

class TenantResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        return Tenant::current();
    }
}
```

**Store-based:**

```php
class StoreResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        // From session
        $storeId = session('current_store_id');
        return Store::find($storeId);
    }
}
```

**Domain-based:**

```php
class DomainResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        $domain = request()->getHost();
        return Merchant::where('domain', $domain)->first();
    }
}
```

## How It Works

### Creating Vouchers

When owner scoping is enabled and `auto_assign_on_create` is true:

```php
// Voucher is automatically assigned to current owner
$voucher = Voucher::create([
    'code' => 'MERCHANT10',
    'name' => 'Merchant Discount',
    'type' => VoucherType::Percentage,
    'value' => 1000,
    'currency' => 'MYR',
]);

// owner_type and owner_id are set automatically
```

### Manual Assignment

Explicitly assign owner:

```php
$voucher = Voucher::create([
    'code' => 'STORE50',
    'name' => 'Store Promo',
    'type' => VoucherType::Fixed,
    'value' => 5000,
    'currency' => 'MYR',
    'owner_type' => Store::class,
    'owner_id' => $store->id,
]);
```

### Global Vouchers

Create vouchers without an owner (available to all):

```php
// Disable auto-assign temporarily
$voucher = Voucher::create([
    'code' => 'SITEWIDE',
    'name' => 'Sitewide Promo',
    'type' => VoucherType::Percentage,
    'value' => 500,
    'currency' => 'MYR',
    'owner_type' => null,
    'owner_id' => null,
]);
```

### Querying Vouchers

All voucher queries are automatically scoped:

```php
// Only returns vouchers for current owner (+ global if include_global = true)
$voucher = Voucher::find('MERCHANT10');

// Same scoping applies
$valid = Voucher::isValid('MERCHANT10');
```

## Scope Behavior

| include_global | Owner Resolved | Vouchers Returned |
|----------------|----------------|-------------------|
| `true` | Yes | Owner's + Global |
| `true` | No | Global only |
| `false` | Yes | Owner's only |
| `false` | No | None (no owner = no vouchers) |

## Model Scope

Use the scope directly on the model:

```php
use AIArmada\Vouchers\Models\Voucher;

// Get all vouchers for a specific owner
$merchantVouchers = Voucher::query()
    ->forOwner($merchant, includeGlobal: true)
    ->where('status', 'active')
    ->get();

// Get only global vouchers
$globalVouchers = Voucher::query()
    ->forOwner(null, includeGlobal: true)
    ->get();
```

## Owner Display

Get a human-readable owner name:

```php
$voucher = Voucher::find($id);

// Attempts: name, display_name, email, or ClassName:id
$ownerName = $voucher->owner_display_name;
```

## Validation with Owners

The validator respects owner scoping:

```php
// Only validates if voucher belongs to current owner (or is global)
$result = Voucher::validate('MERCHANT10', $cart);

if (!$result->isValid) {
    // Could be "Voucher not found" if wrong owner
    echo $result->reason;
}
```

## Marketplace Example

Complete multi-vendor marketplace setup:

```php
// config/vouchers.php
'owner' => [
    'enabled' => true,
    'include_global' => true, // Platform-wide promos
    'auto_assign_on_create' => true,
],
```

```env
VOUCHERS_OWNER_ENABLED=true
COMMERCE_OWNER_RESOLVER=App\Support\Vouchers\VendorResolver
```

```php
// app/Support/Vouchers/VendorResolver.php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

class VendorResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        // In vendor dashboard context
        if (auth()->guard('vendor')->check()) {
            return auth()->guard('vendor')->user();
        }
        
        // In storefront context (viewing vendor's store)
        if ($vendorId = request()->route('vendor')) {
            return Vendor::find($vendorId);
        }
        
        return null;
    }
}
```

```php
// VendorDashboardController
public function createVoucher(Request $request)
{
    // Voucher automatically assigned to current vendor
    $voucher = Voucher::create([
        'code' => $request->code,
        'name' => $request->name,
        'type' => $request->type,
        'value' => $request->value,
        'currency' => 'MYR',
    ]);
    
    return redirect()->route('vendor.vouchers.index');
}

// StorefrontController
public function applyVoucher(Request $request, Vendor $vendor)
{
    // Resolver returns $vendor based on route parameter
    // Only this vendor's vouchers (+ global) are available
    
    try {
        Cart::applyVoucher($request->code);
    } catch (InvalidVoucherException $e) {
        // Voucher not found (or belongs to different vendor)
    }
}
```

## Switching Context

If you need to query vouchers for a different owner:

```php
use AIArmada\Vouchers\Models\Voucher;

// Bypass the resolver and query directly
$otherMerchantVouchers = Voucher::query()
    ->where('owner_type', Merchant::class)
    ->where('owner_id', $otherMerchant->id)
    ->get();
```
