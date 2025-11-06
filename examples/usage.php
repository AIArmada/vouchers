<?php

declare(strict_types=1);

/**
 * Voucher System Usage Examples
 *
 * This file demonstrates common voucher usage patterns with the cart.
 */

use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Vouchers\Enums\VoucherType;
use AIArmada\Cart\Vouchers\Facades\Voucher;

// ============================================================================
// Example 1: Basic Percentage Voucher
// ============================================================================

// Create a percentage voucher
Voucher::create([
    'code' => 'SUMMER20',
    'type' => VoucherType::Percentage,
    'value' => 20,
    'description' => '20% off summer sale',
    'starts_at' => now(),
    'expires_at' => now()->addMonth(),
]);

// Add items to cart
Cart::add($product, quantity: 2); // $100 subtotal

// Apply voucher
Cart::applyVoucher('SUMMER20');

// Get totals
$subtotal = Cart::subtotal(); // 100.00
$discount = Cart::getVoucherDiscount(); // 20.00
$total = Cart::total(); // 80.00

// ============================================================================
// Example 2: Fixed Amount Voucher with Minimum
// ============================================================================

Voucher::create([
    'code' => 'SAVE50',
    'type' => VoucherType::Fixed,
    'value' => 50,
    'description' => '$50 off orders over $200',
    'min_cart_value' => 200,
    'starts_at' => now(),
    'expires_at' => now()->addWeek(),
]);

Cart::add($expensiveProduct, quantity: 1); // $250 subtotal
Cart::applyVoucher('SAVE50');

$total = Cart::total(); // 200.00

// ============================================================================
// Example 3: Free Shipping Voucher
// ============================================================================

Voucher::create([
    'code' => 'FREESHIP',
    'type' => VoucherType::FreeShipping,
    'value' => 0,
    'description' => 'Free shipping on all orders',
]);

Cart::add($product);
Cart::applyVoucher('FREESHIP');

// Check if free shipping applied
$voucher = Cart::getVoucherCondition('FREESHIP');
if ($voucher && $voucher->isFreeShipping()) {
    // Apply free shipping logic in checkout
    $shippingCost = 0;
}

// ============================================================================
// Example 4: Limited Use Voucher
// ============================================================================

Voucher::create([
    'code' => 'FIRSTBUY',
    'type' => VoucherType::Percentage,
    'value' => 15,
    'description' => 'First purchase discount',
    'max_uses' => 100, // Total 100 uses
    'max_uses_per_user' => 1, // One per user
]);

Cart::applyVoucher('FIRSTBUY');

// After order is paid
Voucher::recordUsage('FIRSTBUY', Cart::instance(), auth()->id());

// ============================================================================
// Example 5: Voucher with Maximum Discount Cap
// ============================================================================

Voucher::create([
    'code' => 'MEGA50',
    'type' => VoucherType::Percentage,
    'value' => 50, // 50% off
    'description' => '50% off (max $100 discount)',
    'max_discount_amount' => 100,
]);

Cart::add($luxuryProduct); // $500 subtotal
Cart::applyVoucher('MEGA50');

// Discount capped at $100
$discount = Cart::getVoucherDiscount(); // 100.00 (not 250.00)
$total = Cart::total(); // 400.00

// ============================================================================
// Example 6: Multiple Vouchers (Stacking)
// ============================================================================

// Configure in config/vouchers.php:
// 'max_vouchers_per_cart' => 2,
// 'allow_stacking' => true,

Cart::add($product); // $100 subtotal

Cart::applyVoucher('SAVE10'); // 10% off = $10
Cart::applyVoucher('EXTRA5'); // 5% off = $4.50 (of remaining $90)

$discount = Cart::getVoucherDiscount(); // 14.50
$total = Cart::total(); // 85.50

// ============================================================================
// Example 7: Checking Applied Vouchers
// ============================================================================

// Check if any voucher is applied
if (Cart::hasVoucher()) {
    // Cart has at least one voucher
}

// Check if specific voucher is applied
if (Cart::hasVoucher('SUMMER20')) {
    // SUMMER20 is applied
}

// Get all applied voucher codes
$codes = Cart::getAppliedVoucherCodes(); // ['SUMMER20', 'EXTRA5']

// Get all voucher conditions
$vouchers = Cart::getAppliedVouchers(); // [VoucherCondition, ...]

// Get total discount from vouchers
$discount = Cart::getVoucherDiscount(); // 34.50

// ============================================================================
// Example 8: Removing Vouchers
// ============================================================================

// Remove specific voucher
Cart::removeVoucher('SUMMER20');

// Remove all vouchers
Cart::clearVouchers();

// ============================================================================
// Example 9: Validating Vouchers After Cart Changes
// ============================================================================

Cart::add($product); // $150 subtotal

// Apply voucher with minimum
Cart::applyVoucher('SAVE50'); // min_cart_value = 200, will throw exception

// Meet minimum
Cart::add($product); // $300 subtotal
Cart::applyVoucher('SAVE50'); // Success!

// Remove item
Cart::remove($product->id); // $150 subtotal

// Re-validate vouchers
$removed = Cart::validateAppliedVouchers();

if (count($removed) > 0) {
    // SAVE50 was removed because cart fell below minimum
    session()->flash('warning', 'Voucher removed: cart below minimum value');
}

// ============================================================================
// Example 10: Error Handling
// ============================================================================

use AIArmada\Cart\Vouchers\Exceptions\InvalidVoucherException;

try {
    Cart::applyVoucher($userInput);

    return response()->json(['message' => 'Voucher applied successfully']);
} catch (InvalidVoucherException $e) {
    return response()->json([
        'error' => $e->getMessage(),
    ], 422);
}

// ============================================================================
// Example 11: Usage History
// ============================================================================

// Get all usage for a voucher
$history = Voucher::getUsageHistory('SUMMER20');

foreach ($history as $usage) {
    echo "Used by user {$usage->user_id} on {$usage->created_at}";
    echo "Cart value: {$usage->cart_snapshot['subtotal']}";
}

// ============================================================================
// Example 12: Can Add More Vouchers?
// ============================================================================

if (Cart::canAddVoucher()) {
    // User can add another voucher
    echo 'You can add '.(config('vouchers.cart.max_vouchers_per_cart') - count(Cart::getAppliedVouchers())).' more voucher(s)';
} else {
    echo 'Maximum vouchers reached';
}

// ============================================================================
// Example 13: Voucher in Blade Template
// ============================================================================

/*
<div class="cart-summary">
    <div>Subtotal: ${{ number_format(Cart::subtotal(), 2) }}</div>

    @if(Cart::hasVoucher())
        <div class="voucher-discount">
            Voucher Discount: -${{ number_format(Cart::getVoucherDiscount(), 2) }}

            @foreach(Cart::getAppliedVouchers() as $voucher)
                <span class="badge">{{ $voucher->getVoucherCode() }}</span>
                <button wire:click="removeVoucher('{{ $voucher->getVoucherCode() }}')">Remove</button>
            @endforeach
        </div>
    @endif

    <div class="total">Total: ${{ number_format(Cart::total(), 2) }}</div>
</div>

<form wire:submit.prevent="applyVoucher">
    <input type="text" wire:model="voucherCode" placeholder="Enter voucher code">
    <button type="submit">Apply</button>
</form>
*/

// ============================================================================
// Example 14: Livewire Component for Vouchers
// ============================================================================

/*
<?php

use Livewire\Component;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Vouchers\Exceptions\InvalidVoucherException;

class CartVouchers extends Component
{
    public string $voucherCode = '';

    public function applyVoucher()
    {
        $this->validate([
            'voucherCode' => 'required|string|min:4',
        ]);

        try {
            Cart::applyVoucher($this->voucherCode);

            session()->flash('success', 'Voucher applied successfully!');
            $this->voucherCode = '';
        } catch (InvalidVoucherException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function removeVoucher(string $code)
    {
        Cart::removeVoucher($code);
        session()->flash('success', 'Voucher removed');
    }

    public function render()
    {
        return view('livewire.cart-vouchers', [
            'appliedVouchers' => Cart::getAppliedVouchers(),
            'canAddMore' => Cart::canAddVoucher(),
            'discount' => Cart::getVoucherDiscount(),
        ]);
    }
}
*/

// ============================================================================
// Example 15: Event Listeners for Vouchers
// ============================================================================

/*
// app/Listeners/RecordVoucherUsage.php

use AIArmada\Cart\Vouchers\Events\VoucherApplied;
use AIArmada\Cart\Vouchers\Facades\Voucher;

class RecordVoucherUsage
{
    public function handle(VoucherApplied $event): void
    {
        // Record usage immediately (or wait for order completion)
        Voucher::recordUsage(
            code: $event->voucher->code,
            cart: $event->cart,
            userId: auth()->id()
        );

        // Send analytics
        Analytics::track('voucher_applied', [
            'code' => $event->voucher->code,
            'type' => $event->voucher->type->value,
            'value' => $event->voucher->value,
            'cart_total' => $event->cart->total(),
        ]);
    }
}
*/
