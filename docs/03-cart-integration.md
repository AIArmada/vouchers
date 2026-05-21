---
title: Cart Integration
---

# Cart Integration

The vouchers package integrates seamlessly with AIArmada Cart through the `InteractsWithVouchers` trait.

## Applying Vouchers

```php
use AIArmada\Cart\Facades\Cart;

try {
    Cart::applyVoucher('SUMMER2024');
} catch (\AIArmada\Vouchers\Exceptions\InvalidVoucherException $e) {
    // Handle invalid voucher
    echo $e->getMessage();
}
```

### With Custom Order

The order parameter determines when the voucher is calculated relative to other cart conditions:

```php
// Apply voucher with order 50 (default is 100)
Cart::applyVoucher('SUMMER2024', 50);
```

Lower order = applied earlier in the calculation chain.

## Removing Vouchers

```php
// Remove specific voucher
Cart::removeVoucher('SUMMER2024');

// Remove all vouchers
Cart::clearVouchers();
```

## Checking Vouchers

```php
// Check if any voucher is applied
if (Cart::hasVoucher()) {
    echo "Cart has vouchers";
}

// Check for specific voucher
if (Cart::hasVoucher('SUMMER2024')) {
    echo "SUMMER2024 is applied";
}

// Get applied voucher codes
$codes = Cart::getAppliedVoucherCodes();
// ['SUMMER2024', 'FREESHIP']

// Get voucher conditions
$vouchers = Cart::getAppliedVouchers();
foreach ($vouchers as $voucherCondition) {
    echo $voucherCondition->getVoucherCode();
    echo $voucherCondition->getCalculatedValue($subtotal);
}
```

## Getting Voucher Discount

```php
// Get total discount from all vouchers
$discount = Cart::getVoucherDiscount();
echo "You save: RM" . number_format($discount / 100, 2);
```

## Voucher Limits

```php
// Check if cart can accept more vouchers
if (Cart::canAddVoucher()) {
    // Show voucher input
}

// Get remaining slots (based on max_vouchers_per_cart config)
$maxVouchers = config('vouchers.cart.max_vouchers_per_cart');
$currentCount = count(Cart::getAppliedVouchers());
$remaining = $maxVouchers - $currentCount;
```

## Validating Applied Vouchers

After modifying the cart, validate that vouchers are still applicable:

```php
// Remove items from cart
Cart::remove($itemId);

// Validate vouchers (removes invalid ones)
$removedVouchers = Cart::validateAppliedVouchers();

if (count($removedVouchers) > 0) {
    echo "Some vouchers were removed: " . implode(', ', $removedVouchers);
}
```

## Multiple Vouchers

### Configuration

```php
// config/vouchers.php
'cart' => [
    'max_vouchers_per_cart' => 3, // Allow up to 3 vouchers
    'replace_when_max_reached' => false, // Don't replace, throw error
    'allow_stacking' => true, // Apply sequentially
],
```

### Stacking Behavior

When `allow_stacking` is enabled, vouchers are applied sequentially:

```php
// Cart subtotal: RM100.00

// Apply 20% discount first
Cart::applyVoucher('SAVE20'); // -RM20.00, subtotal now RM80.00

// Apply RM10 discount next
Cart::applyVoucher('FLAT10'); // -RM10.00, subtotal now RM70.00

// Total discount: RM30.00
```

## Condition Order

Control when vouchers are calculated:

```php
// config/vouchers.php
'cart' => [
    'condition_order' => 50, // Vouchers calculate after order 50
],
```

Example calculation chain:
1. Fee (order 25) — +RM5.00
2. Voucher (order 50) — -20%
3. Shipping (order 75) — +RM10.00
4. Tax (order 100) — +6%

## Events

Listen for voucher events:

```php
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Events\VoucherRemoved;

Event::listen(VoucherApplied::class, function ($event) {
    $cart = $event->cart;
    $voucherData = $event->voucher;
    
    // Log or track application
    Log::info("Voucher {$voucherData->code} applied to cart");
});

Event::listen(VoucherRemoved::class, function ($event) {
    $cart = $event->cart;
    $voucherData = $event->voucher;
    
    Log::info("Voucher {$voucherData->code} removed from cart");
});
```

## Exception Handling

```php
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Exceptions\VoucherExpiredException;
use AIArmada\Vouchers\Exceptions\VoucherUsageLimitException;

try {
    Cart::applyVoucher($code);
    
    return response()->json([
        'success' => true,
        'discount' => Cart::getVoucherDiscount(),
    ]);
} catch (VoucherNotFoundException $e) {
    return response()->json([
        'error' => 'Voucher not found',
    ], 404);
} catch (VoucherExpiredException $e) {
    return response()->json([
        'error' => 'Voucher has expired',
    ], 422);
} catch (VoucherUsageLimitException $e) {
    return response()->json([
        'error' => 'Voucher usage limit reached',
    ], 422);
} catch (InvalidVoucherException $e) {
    return response()->json([
        'error' => $e->getMessage(),
    ], 422);
}
```

## Cart Totals with Vouchers

```php
// Get totals
$subtotal = Cart::subtotal();       // Before conditions
$total = Cart::getTotal();           // After all conditions
$voucherDiscount = Cart::getVoucherDiscount();

// Display
echo "Subtotal: " . $subtotal->format();
echo "Discount: -" . number_format($voucherDiscount / 100, 2);
echo "Total: " . $total->format();
```

## Persistence

Applied vouchers are stored in the cart session and persist across page loads. When the cart is restored from the session, voucher conditions are automatically restored.

```php
// Vouchers persist with cart
Cart::applyVoucher('SUMMER2024');

// ... user navigates away and returns ...

// Voucher is still applied
Cart::hasVoucher('SUMMER2024'); // true
```

## Affiliates Integration

When `aiarmada/affiliates` is installed, the vouchers package can auto-create vouchers for
affiliates and attach affiliate data from voucher metadata.

```php
// config/vouchers.php
'affiliates' => [
    'enabled' => true,
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
