---
title: Usage Tracking
---

# Usage Tracking & Analytics

The vouchers package tracks voucher applications and redemptions for analytics and reporting.

## Application Tracking

When a voucher is applied to a cart, the `applied_count` is incremented:

```php
// config/vouchers.php
'tracking' => [
    'track_applications' => true, // Enable/disable
],
```

This allows tracking how many times a voucher was *considered* vs actually *used*.

## Voucher Statistics

```php
use AIArmada\Vouchers\Models\Voucher;

$voucher = Voucher::where('code', 'SUMMER2024')->first();

// Get comprehensive statistics
$stats = $voucher->getStatistics();
// [
//     'applied_count' => 150,    // Times added to cart
//     'redeemed_count' => 75,    // Times actually used
//     'abandoned_count' => 75,   // Applied but not redeemed
//     'conversion_rate' => 50.0, // Percentage
//     'remaining_uses' => 925,   // If usage_limit set
// ]
```

### Individual Metrics

```php
// Conversion rate (null if never applied)
$rate = $voucher->getConversionRate(); // e.g., 45.5

// Abandoned applications
$abandoned = $voucher->getAbandonedCount();

// Times actually used
$used = $voucher->times_used;

// Remaining uses (null if no limit)
$remaining = $voucher->getRemainingUses();

// Usage progress percentage
$progress = $voucher->usage_progress; // e.g., 75.0 (75% used)
```

## Usage History

Get detailed redemption history:

```php
use AIArmada\Vouchers\Facades\Voucher;

$history = Voucher::getUsageHistory('SUMMER2024');

foreach ($history as $usage) {
    echo "Date: " . $usage->used_at->format('d M Y H:i');
    echo "Amount: " . number_format($usage->discount_amount / 100, 2);
    echo "Currency: " . $usage->currency;
    echo "Channel: " . $usage->channel;
    
    if ($usage->redeemedBy) {
        echo "User: " . $usage->redeemedBy->name;
    }
    
    if ($usage->notes) {
        echo "Notes: " . $usage->notes;
    }
    
    if ($usage->metadata) {
        echo "Order: " . ($usage->metadata['order_id'] ?? 'N/A');
    }
}
```

## VoucherUsage Model

```php
use AIArmada\Vouchers\Models\VoucherUsage;

// Query usage records
$usages = VoucherUsage::query()
    ->with(['voucher', 'redeemedBy'])
    ->where('channel', 'web')
    ->whereBetween('used_at', [$startDate, $endDate])
    ->get();

// Aggregate queries
$totalRedemptions = VoucherUsage::count();

$totalDiscount = VoucherUsage::where('currency', 'MYR')
    ->sum('discount_amount'); // In cents

$avgDiscount = VoucherUsage::where('currency', 'MYR')
    ->avg('discount_amount');
```

### Usage Properties

| Property | Type | Description |
|----------|------|-------------|
| `voucher_id` | uuid | Related voucher |
| `discount_amount` | int | Discount in cents |
| `currency` | string | Currency code |
| `channel` | string | Redemption channel |
| `used_at` | datetime | When redeemed |
| `redeemed_by_type` | string | User model type |
| `redeemed_by_id` | mixed | User ID |
| `metadata` | array | Additional data |
| `notes` | string | Human-readable notes |
| `target_definition` | array | Voucher targeting at time of use |

## Reporting Queries

### By Time Period

```php
// Daily redemptions for the last 30 days
$daily = VoucherUsage::query()
    ->selectRaw('DATE(used_at) as date, COUNT(*) as count, SUM(discount_amount) as total')
    ->where('used_at', '>=', now()->subDays(30))
    ->groupByRaw('DATE(used_at)')
    ->orderBy('date')
    ->get();
```

### By Channel

```php
// Redemptions by channel
$byChannel = VoucherUsage::query()
    ->selectRaw('channel, COUNT(*) as count, SUM(discount_amount) as total')
    ->groupBy('channel')
    ->get();
```

### Top Vouchers

```php
// Most redeemed vouchers
$topVouchers = Voucher::query()
    ->withCount('usages')
    ->orderByDesc('usages_count')
    ->limit(10)
    ->get();
```

### By User

```php
// User redemption summary
$userStats = VoucherUsage::query()
    ->selectRaw('redeemed_by_type, redeemed_by_id, COUNT(*) as redemptions, SUM(discount_amount) as total_discount')
    ->whereNotNull('redeemed_by_id')
    ->groupBy('redeemed_by_type', 'redeemed_by_id')
    ->orderByDesc('total_discount')
    ->limit(20)
    ->get();
```

## Campaign Analysis

```php
// Analyze campaign performance
$campaign = 'summer-2024';

$vouchers = Voucher::query()
    ->where('metadata->campaign', $campaign)
    ->get();

$stats = [
    'total_vouchers' => $vouchers->count(),
    'total_applied' => $vouchers->sum('applied_count'),
    'total_redeemed' => $vouchers->sum(fn ($v) => $v->times_used),
    'total_remaining' => $vouchers->sum(fn ($v) => $v->getRemainingUses() ?? 0),
    'avg_conversion' => $vouchers
        ->filter(fn ($v) => $v->getConversionRate() !== null)
        ->avg(fn ($v) => $v->getConversionRate()),
];
```

## Dashboard Example

```php
class VoucherDashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.vouchers', [
            // Summary stats
            'totalActive' => Voucher::where('status', 'active')->count(),
            'totalRedemptions' => VoucherUsage::count(),
            'totalDiscountGiven' => VoucherUsage::sum('discount_amount'),
            
            // Recent activity
            'recentRedemptions' => VoucherUsage::with(['voucher', 'redeemedBy'])
                ->latest('used_at')
                ->limit(10)
                ->get(),
            
            // Top performers
            'topVouchers' => Voucher::withCount('usages')
                ->orderByDesc('usages_count')
                ->limit(5)
                ->get(),
            
            // Low performers
            'lowConversion' => Voucher::where('applied_count', '>', 10)
                ->get()
                ->filter(fn ($v) => $v->getConversionRate() < 20)
                ->sortBy(fn ($v) => $v->getConversionRate())
                ->take(5),
            
            // Expiring soon
            'expiringSoon' => Voucher::where('status', 'active')
                ->where('expires_at', '<=', now()->addWeek())
                ->where('expires_at', '>', now())
                ->get(),
        ]);
    }
}
```

## Events for Analytics

Listen to voucher events for real-time analytics:

```php
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Events\VoucherRemoved;

// Track applications
Event::listen(VoucherApplied::class, function ($event) {
    Analytics::track('voucher_applied', [
        'voucher_code' => $event->voucher->code,
        'voucher_type' => $event->voucher->type->value,
        'session_id' => session()->getId(),
    ]);
});

// Track removals (potential issues with voucher)
Event::listen(VoucherRemoved::class, function ($event) {
    Analytics::track('voucher_removed', [
        'voucher_code' => $event->voucher->code,
        'session_id' => session()->getId(),
    ]);
});
```
