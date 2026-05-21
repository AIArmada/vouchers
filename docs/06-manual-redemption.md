---
title: Manual Redemption
---

# Manual Redemption

Manual redemption allows vouchers to be redeemed outside the normal cart checkout flow. This is useful for:

- Point-of-sale (POS) transactions
- Phone/call center orders
- Admin-initiated redemptions
- Promotional events
- Gift card redemptions

## Configuration

```php
// config/vouchers.php
'redemption' => [
    // Require allows_manual_redemption flag on voucher
    'manual_requires_flag' => true,
    
    // Channel name for manual redemptions
    'manual_channel' => 'manual',
],
```

### Safety Flag

When `manual_requires_flag` is `true` (recommended), vouchers must explicitly allow manual redemption:

```php
// This voucher CAN be manually redeemed
$voucher = Voucher::create([
    'code' => 'GIFTCARD',
    'name' => 'Gift Card',
    'type' => VoucherType::Fixed,
    'value' => 10000,
    'currency' => 'MYR',
    'allows_manual_redemption' => true,
]);

// This voucher CANNOT be manually redeemed
$voucher = Voucher::create([
    'code' => 'FIRSTORDER',
    'name' => 'First Order Discount',
    'type' => VoucherType::Percentage,
    'value' => 2000,
    'currency' => 'MYR',
    'allows_manual_redemption' => false, // or omit (default false)
]);
```

## Basic Usage

```php
use AIArmada\Vouchers\Facades\Voucher;
use Akaunting\Money\Money;

Voucher::redeemManually(
    code: 'GIFTCARD',
    discountAmount: Money::MYR(5000), // RM50.00
);
```

## With Full Options

```php
Voucher::redeemManually(
    code: 'GIFTCARD',
    discountAmount: Money::MYR(5000),
    reference: 'POS-12345',           // External reference (e.g., receipt number)
    metadata: [                        // Additional data
        'terminal' => 'store-a-01',
        'location' => 'Kuala Lumpur',
    ],
    redeemedBy: $staff,                // Staff member processing the redemption
    notes: 'In-store purchase',        // Human-readable notes
);
```

## Exception Handling

```php
use AIArmada\Vouchers\Exceptions\ManualRedemptionNotAllowedException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;

try {
    Voucher::redeemManually(
        code: $code,
        discountAmount: Money::MYR($amount),
        reference: $receiptNumber,
    );
    
    return response()->json(['success' => true]);
    
} catch (VoucherNotFoundException $e) {
    return response()->json(['error' => 'Voucher not found'], 404);
    
} catch (ManualRedemptionNotAllowedException $e) {
    return response()->json([
        'error' => 'This voucher cannot be manually redeemed',
    ], 422);
}
```

## Usage Record

Manual redemptions create a `VoucherUsage` record:

```php
// After manual redemption, check usage history
$history = Voucher::getUsageHistory('GIFTCARD');

foreach ($history as $usage) {
    echo $usage->channel;           // 'manual'
    echo $usage->discount_amount;   // 5000 (cents)
    echo $usage->currency;          // 'MYR'
    echo $usage->used_at;           // Carbon datetime
    echo $usage->notes;             // 'In-store purchase'
    
    // Reference is stored in metadata
    echo $usage->metadata['reference']; // 'POS-12345'
    
    // Staff who processed it
    if ($usage->redeemedBy) {
        echo $usage->redeemedBy->name;
    }
}
```

## POS Integration Example

```php
class POSController extends Controller
{
    public function redeemVoucher(Request $request)
    {
        $request->validate([
            'voucher_code' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'receipt_number' => 'required|string',
        ]);
        
        $staff = auth()->user();
        $terminal = $request->header('X-Terminal-ID');
        
        try {
            // Validate voucher first
            if (!Voucher::isValid($request->voucher_code)) {
                return response()->json([
                    'error' => 'Invalid or expired voucher',
                ], 422);
            }
            
            // Get remaining value (for gift cards)
            $remaining = Voucher::getRemainingUses($request->voucher_code);
            
            if ($remaining < 1) {
                return response()->json([
                    'error' => 'Voucher fully redeemed',
                ], 422);
            }
            
            // Process redemption
            Voucher::redeemManually(
                code: $request->voucher_code,
                discountAmount: Money::MYR($request->amount * 100),
                reference: $request->receipt_number,
                metadata: [
                    'terminal' => $terminal,
                    'store' => $staff->store_id,
                ],
                redeemedBy: $staff,
            );
            
            return response()->json([
                'success' => true,
                'remaining_uses' => Voucher::getRemainingUses($request->voucher_code),
            ]);
            
        } catch (ManualRedemptionNotAllowedException $e) {
            return response()->json([
                'error' => 'Voucher not eligible for in-store use',
            ], 422);
        }
    }
}
```

## Admin Panel Example

```php
class AdminVoucherController extends Controller
{
    public function manualRedeem(Request $request, string $voucherCode)
    {
        $request->validate([
            'customer_email' => 'required|email',
            'order_id' => 'required|string',
            'discount_amount' => 'required|numeric|min:0',
            'reason' => 'required|string',
        ]);
        
        $admin = auth()->user();
        $customer = User::where('email', $request->customer_email)->first();
        
        Voucher::redeemManually(
            code: $voucherCode,
            discountAmount: Money::MYR($request->discount_amount * 100),
            reference: $request->order_id,
            metadata: [
                'customer_email' => $request->customer_email,
                'customer_id' => $customer?->id,
                'admin_action' => true,
            ],
            redeemedBy: $admin,
            notes: $request->reason,
        );
        
        return redirect()->back()->with('success', 'Voucher redeemed successfully');
    }
}
```

## Recording Usage Without Manual Redemption

For cart-based redemptions, use `recordUsage` directly:

```php
// After successful checkout
Voucher::recordUsage(
    code: 'SUMMER2024',
    discountAmount: Money::MYR(2500),
    channel: 'web',
    metadata: [
        'order_id' => $order->id,
        'ip_address' => request()->ip(),
    ],
    redeemedBy: auth()->user(),
);
```

## Channel Types

Common channel values for tracking:

| Channel | Description |
|---------|-------------|
| `web` | Online checkout |
| `mobile` | Mobile app |
| `manual` | Manual redemption (default) |
| `pos` | Point of sale |
| `api` | API integration |
| `admin` | Admin panel |
