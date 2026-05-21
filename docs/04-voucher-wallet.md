---
title: Voucher Wallet
---

# Voucher Wallet

The voucher wallet allows users to save vouchers for later use. This is useful for loyalty programs, gift vouchers, and promotional campaigns.

## Setup

Add the `HasVouchers` trait to your User model (or any model that should have a wallet):

```php
use AIArmada\Vouchers\Traits\HasVouchers;

class User extends Authenticatable
{
    use HasVouchers;
}
```

## Adding Vouchers

```php
// Add voucher to user's wallet
$user->addVoucherToWallet('SUMMER2024');

// Or via facade
use AIArmada\Vouchers\Facades\Voucher;

$walletEntry = Voucher::addToWallet('SUMMER2024', $user, [
    'source' => 'referral-program',
    'campaign' => 'summer-2024',
]);
```

## Checking Wallet

```php
// Check if voucher exists in wallet
if ($user->hasVoucherInWallet('SUMMER2024')) {
    echo "Voucher is in wallet";
}
```

## Retrieving Vouchers

### Available Vouchers

Get vouchers that can be used:

```php
$available = $user->getAvailableVouchers();

foreach ($available as $walletEntry) {
    echo $walletEntry->voucher->code;
    echo $walletEntry->voucher->name;
    
    if ($walletEntry->canBeUsed()) {
        // Show "Apply" button
    }
}
```

### Redeemed Vouchers

Get vouchers that have been used:

```php
$redeemed = $user->getRedeemedVouchers();

foreach ($redeemed as $walletEntry) {
    echo $walletEntry->voucher->code;
    echo "Used on: " . $walletEntry->redeemed_at->format('d M Y');
}
```

### Expired Vouchers

Get vouchers that have expired:

```php
$expired = $user->getExpiredVouchers();

foreach ($expired as $walletEntry) {
    echo $walletEntry->voucher->code;
    echo "Expired: " . $walletEntry->voucher->expires_at->format('d M Y');
}
```

## Wallet Entry Status

```php
$walletEntry = $user->voucherWallets()->first();

// Check availability
$walletEntry->isAvailable();  // Claimed but not redeemed
$walletEntry->isExpired();    // Voucher has expired
$walletEntry->canBeUsed();    // Available, not expired, voucher active
```

## Marking as Redeemed

When a voucher is used, mark it as redeemed:

```php
// Via trait method
$user->markVoucherAsRedeemed('SUMMER2024');

// Or directly on wallet entry
$walletEntry->markAsRedeemed();
```

## Removing from Wallet

Remove a voucher from the wallet (only if not redeemed):

```php
// Via trait method
$removed = $user->removeVoucherFromWallet('SUMMER2024');

if (!$removed) {
    echo "Voucher cannot be removed (already redeemed)";
}

// Or via facade
Voucher::removeFromWallet('SUMMER2024', $user);
```

## Wallet Entry Properties

```php
$walletEntry = $user->voucherWallets()->first();

$walletEntry->voucher_id;     // UUID of the voucher
$walletEntry->owner_type;     // e.g., 'App\Models\User'
$walletEntry->owner_id;       // User ID
$walletEntry->is_claimed;     // bool
$walletEntry->claimed_at;     // Carbon datetime
$walletEntry->is_redeemed;    // bool
$walletEntry->redeemed_at;    // Carbon datetime
$walletEntry->metadata;       // array
```

## Wallet with Metadata

Store additional data with wallet entries:

```php
$walletEntry = Voucher::addToWallet('GIFTCARD', $user, [
    'gifted_by' => $sender->id,
    'message' => 'Happy Birthday!',
    'occasion' => 'birthday',
]);

// Access metadata later
$metadata = $walletEntry->metadata;
echo "From: " . User::find($metadata['gifted_by'])->name;
echo "Message: " . $metadata['message'];
```

## Integration with Cart

Use wallet vouchers in the cart:

```php
// Get available vouchers for display
$vouchers = $user->getAvailableVouchers();

// User selects a voucher
$selectedCode = $vouchers->first()->voucher->code;

// Apply to cart
Cart::applyVoucher($selectedCode);

// After checkout, mark as redeemed
$user->markVoucherAsRedeemed($selectedCode);
```

## Complete Example

```php
class CheckoutController extends Controller
{
    public function showVouchers()
    {
        $user = auth()->user();
        
        return view('checkout.vouchers', [
            'available' => $user->getAvailableVouchers(),
            'applied' => Cart::getAppliedVoucherCodes(),
        ]);
    }
    
    public function applyVoucher(Request $request)
    {
        $code = $request->input('code');
        $user = auth()->user();
        
        // Verify voucher is in user's wallet
        if (!$user->hasVoucherInWallet($code)) {
            return back()->with('error', 'Voucher not in your wallet');
        }
        
        try {
            Cart::applyVoucher($code);
            return back()->with('success', 'Voucher applied');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    
    public function completeCheckout()
    {
        $user = auth()->user();
        
        // Mark applied vouchers as redeemed
        foreach (Cart::getAppliedVoucherCodes() as $code) {
            if ($user->hasVoucherInWallet($code)) {
                $user->markVoucherAsRedeemed($code);
            }
        }
        
        // Continue with order processing...
    }
}
```

## Credit System (Advanced)

For balance-based vouchers (like gift cards with stored value), use the assignment and transaction features:

```php
// Assign voucher and grant initial credit
$user->assignAndCreditVoucher($voucher, 10000, 'Gift Card Purchase');

// Check balance
$balance = $user->voucherBalance($voucher);
echo "Balance: RM" . number_format($balance / 100, 2);

// Grant additional credit
$user->grantVoucherCredit($voucher, 2500, 'Bonus Credit');

// Redeem (deducts from balance)
if ($user->canRedeemVoucher($voucher, 5000)) {
    $usage = $user->redeemVoucher($voucher, 5000);
}

// Check transaction history
$transactions = $user->voucherTransactions()
    ->where('voucher_id', $voucher->id)
    ->orderByDesc('created_at')
    ->get();
```
