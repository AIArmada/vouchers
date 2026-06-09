<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WeakMap;

final class AffiliateReportingContextResolver
{
    /**
     * @var array<string, string|null>
     */
    private array $orderNumberCache = [];

    private WeakMap $usageContextCache;

    /**
     * @var array<string, array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }>
     */
    private array $lookupCache = [];

    public function __construct()
    {
        $this->usageContextCache = new WeakMap;
    }

    /**
     * @return array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }
     */
    public function resolve(VoucherUsage $usage): array
    {
        if (isset($this->usageContextCache[$usage])) {
            $context = $this->usageContextCache[$usage];
            if (\is_array($context)) {
                return $context;
            }
        }

        $voucherCode = $this->resolveVoucherCode($usage);

        if ($voucherCode === null || ! $this->isAvailable()) {
            return $this->cache($usage, $this->emptyContext());
        }

        $orderReference = $this->resolveOrderReference($usage);
        $cacheKey = $this->scopeCacheKey() . '|' . $voucherCode . '|' . ($orderReference ?? 'none');

        if (array_key_exists($cacheKey, $this->lookupCache)) {
            return $this->cache($usage, $this->lookupCache[$cacheKey]);
        }

        $context = $this->emptyContext();
        $conversion = $this->findMatchingConversion($voucherCode, $orderReference);

        if ($conversion !== null) {
            $context = $this->contextFromConversion($conversion);
        }

        if (! $this->hasTrackedData($context)) {
            $attribution = $this->findMatchingAttribution($voucherCode);

            if ($attribution !== null) {
                $context = $this->contextFromAttribution($attribution);
            }
        }

        $context = $this->normalizeContext($context);
        $this->lookupCache[$cacheKey] = $context;

        return $this->cache($usage, $context);
    }

    public function supportsAffiliateReporting(): bool
    {
        return $this->isAvailable();
    }

    /**
     * @return array<string, string>
     */
    public function affiliateOptions(): array
    {
        if ($this->hasTableFor(Affiliate::class)) {
            /** @var array<string, string> $options */
            $options = Affiliate::query()
                ->orderBy('name')
                ->orderBy('code')
                ->get(['code', 'name'])
                ->mapWithKeys(function (Model $affiliate): array {
                    $code = $this->normalizeString($affiliate->getAttribute('code'));

                    if ($code === null) {
                        return [];
                    }

                    $name = $this->normalizeString($affiliate->getAttribute('name'));

                    return [$code => $name !== null ? "{$name} ({$code})" : $code];
                })
                ->all();

            return $options;
        }

        if (! $this->hasTableFor(AffiliateAttribution::class)) {
            return [];
        }

        /** @var array<string, string> $options */
        $options = AffiliateAttribution::query()
            ->whereNotNull('affiliate_code')
            ->orderBy('affiliate_code')
            ->pluck('affiliate_code', 'affiliate_code')
            ->all();

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function sourceOptions(): array
    {
        return $this->attributionOptionsFor('source');
    }

    /**
     * @return array<string, string>
     */
    public function mediumOptions(): array
    {
        return $this->attributionOptionsFor('medium');
    }

    /**
     * @return array<string, string>
     */
    public function campaignOptions(): array
    {
        return $this->attributionOptionsFor('campaign');
    }

    /**
     * @param  Builder<VoucherUsage>  $query
     * @param  array<string, mixed>  $criteria
     * @return Builder<VoucherUsage>
     */
    public function applyUsageFilters(Builder $query, array $criteria): Builder
    {
        $normalizedCriteria = $this->normalizeCriteria($criteria);

        if ($normalizedCriteria === [] || ! $this->supportsAffiliateReporting()) {
            return $query;
        }

        $matchingUsageIds = (clone $query)
            ->with(['voucher', 'redeemedBy'])
            ->get()
            ->filter(fn (VoucherUsage $usage): bool => $this->matchesCriteria($this->resolve($usage), $normalizedCriteria))
            ->pluck('id')
            ->all();

        if ($matchingUsageIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($query->qualifyColumn('id'), $matchingUsageIds);
    }

    public function orderId(VoucherUsage $usage): ?string
    {
        $redeemedById = $this->normalizeString((string) ($usage->redeemed_by_id ?? ''));

        if ($redeemedById !== null && $usage->isOrderRedemption()) {
            return $redeemedById;
        }

        $metadata = is_array($usage->metadata) ? $usage->metadata : [];
        $orderId = $metadata['order_id'] ?? null;

        if (! is_scalar($orderId)) {
            return null;
        }

        return $this->normalizeString((string) $orderId);
    }

    public function orderNumber(VoucherUsage $usage): ?string
    {
        if ($usage->isOrderRedemption()) {
            $redeemedBy = $usage->redeemedBy;

            if ($redeemedBy instanceof Model) {
                $orderNumber = $this->normalizeString($redeemedBy->getAttribute('order_number'));

                if ($orderNumber !== null) {
                    return $orderNumber;
                }
            }
        }

        $metadata = is_array($usage->metadata) ? $usage->metadata : [];
        $orderNumber = $metadata['order_number'] ?? null;

        if (is_scalar($orderNumber)) {
            $normalized = $this->normalizeString((string) $orderNumber);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        $orderId = $this->orderId($usage);

        return $orderId !== null ? $this->resolveOrderNumberById($orderId) : null;
    }

    /**
     * @param  array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }  $context
     */
    public function affiliateLabel(array $context): ?string
    {
        $affiliateName = $this->normalizeString($context['affiliate_name']);
        $affiliateCode = $this->normalizeString($context['affiliate_code']);

        if ($affiliateName !== null && $affiliateCode !== null) {
            return "{$affiliateName} ({$affiliateCode})";
        }

        return $affiliateName ?? $affiliateCode;
    }

    /**
     * @param  array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }  $context
     */
    public function sourceMediumLabel(array $context): ?string
    {
        $parts = array_values(array_filter([
            $this->normalizeString($context['source']),
            $this->normalizeString($context['medium']),
        ]));

        if ($parts === []) {
            return null;
        }

        return implode(' / ', $parts);
    }

    /**
     * @param  array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }  $context
     */
    public function hasTrackedData(array $context): bool
    {
        return $this->affiliateLabel($context) !== null
            || $this->sourceMediumLabel($context) !== null
            || $this->normalizeString($context['campaign']) !== null;
    }

    /**
     * @return array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }
     */
    private function emptyContext(): array
    {
        return [
            'affiliate_code' => null,
            'affiliate_name' => null,
            'source' => null,
            'medium' => null,
            'campaign' => null,
        ];
    }

    private function isAvailable(): bool
    {
        return $this->hasTableFor(AffiliateConversion::class) || $this->hasTableFor(AffiliateAttribution::class);
    }

    private function resolveVoucherCode(VoucherUsage $usage): ?string
    {
        $voucher = $usage->relationLoaded('voucher')
            ? $usage->getRelation('voucher')
            : $usage->voucher;

        if (! $voucher instanceof Model) {
            return null;
        }

        $code = $voucher->getAttribute('code');

        return is_string($code)
            ? mb_strtolower($code)
            : null;
    }

    private function resolveOrderReference(VoucherUsage $usage): ?string
    {
        $orderReference = $this->orderNumber($usage);

        return $orderReference !== null ? mb_strtolower($orderReference) : null;
    }

    private function findMatchingConversion(string $voucherCode, ?string $orderReference): ?AffiliateConversion
    {
        if (! $this->hasTableFor(AffiliateConversion::class)) {
            return null;
        }

        $query = AffiliateConversion::query()
            ->with(['affiliate', 'attribution.affiliate'])
            ->whereRaw('LOWER(voucher_code) = ?', [$voucherCode])
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at');

        if ($orderReference !== null) {
            return $query
                ->where(function (Builder $builder) use ($orderReference): void {
                    $builder
                        ->whereRaw('LOWER(order_reference) = ?', [$orderReference])
                        ->orWhereRaw('LOWER(external_reference) = ?', [$orderReference]);
                })
                ->first();
        }

        $matches = $query
            ->limit(2)
            ->get();

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    private function findMatchingAttribution(string $voucherCode): ?AffiliateAttribution
    {
        if (! $this->hasTableFor(AffiliateAttribution::class)) {
            return null;
        }

        return AffiliateAttribution::query()
            ->with(['affiliate'])
            ->whereRaw('LOWER(voucher_code) = ?', [$voucherCode])
            ->orderByDesc('last_seen_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }
     */
    private function contextFromConversion(AffiliateConversion $conversion): array
    {
        $attribution = $conversion->attribution;

        return [
            'affiliate_code' => $this->firstNonEmpty(
                $conversion->affiliate_code,
                $attribution?->affiliate_code,
            ),
            'affiliate_name' => $this->firstNonEmpty(
                $conversion->affiliate?->name,
                $attribution?->affiliate?->name,
            ),
            'source' => $attribution?->source,
            'medium' => $attribution?->medium,
            'campaign' => $attribution?->campaign,
        ];
    }

    /**
     * @return array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }
     */
    private function contextFromAttribution(AffiliateAttribution $attribution): array
    {
        return [
            'affiliate_code' => $attribution->affiliate_code,
            'affiliate_name' => $attribution->affiliate?->name,
            'source' => $attribution->source,
            'medium' => $attribution->medium,
            'campaign' => $attribution->campaign,
        ];
    }

    /**
     * @param  array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }  $context
     * @return array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }
     */
    private function normalizeContext(array $context): array
    {
        return [
            'affiliate_code' => $this->normalizeString($context['affiliate_code']),
            'affiliate_name' => $this->normalizeString($context['affiliate_name']),
            'source' => $this->normalizeString($context['source']),
            'medium' => $this->normalizeString($context['medium']),
            'campaign' => $this->normalizeString($context['campaign']),
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = mb_trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function firstNonEmpty(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $normalized = $this->normalizeString($value);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function attributionOptionsFor(string $column): array
    {
        if (! $this->hasTableFor(AffiliateAttribution::class)) {
            return [];
        }

        /** @var array<string, string> $options */
        $options = AffiliateAttribution::query()
            ->whereNotNull($column)
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();

        return $options;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, string>
     */
    private function normalizeCriteria(array $criteria): array
    {
        $normalized = [];

        foreach (['affiliate_code', 'source', 'medium', 'campaign'] as $key) {
            $value = $criteria[$key] ?? null;

            if (! is_scalar($value)) {
                continue;
            }

            $resolved = $this->normalizeString((string) $value);

            if ($resolved !== null) {
                $normalized[$key] = mb_strtolower($resolved);
            }
        }

        return $normalized;
    }

    /**
     * @param  array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }  $context
     * @param  array<string, string>  $criteria
     */
    private function matchesCriteria(array $context, array $criteria): bool
    {
        foreach ($criteria as $key => $value) {
            $contextValue = $context[$key] ?? null;

            if (! is_string($contextValue) || mb_strtolower($contextValue) !== $value) {
                return false;
            }
        }

        return true;
    }

    private function resolveOrderNumberById(string $orderId): ?string
    {
        if (array_key_exists($orderId, $this->orderNumberCache)) {
            return $this->orderNumberCache[$orderId];
        }

        if (! $this->hasTableFor(Order::class)) {
            $this->orderNumberCache[$orderId] = null;

            return null;
        }

        $order = Order::query()
            ->select(['id', 'order_number'])
            ->find($orderId);

        $orderNumber = $order instanceof Model
            ? $this->normalizeString($order->getAttribute('order_number'))
            : null;

        $this->orderNumberCache[$orderId] = $orderNumber;

        return $orderNumber;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function hasTableFor(string $modelClass): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }

        try {
            $model = new $modelClass;

            return $model instanceof Model && Schema::hasTable($model->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    private function scopeCacheKey(): string
    {
        if (OwnerContext::isExplicitGlobal()) {
            return 'explicit-global';
        }

        $owner = OwnerContext::resolve();

        if (! $owner instanceof Model) {
            return 'no-owner';
        }

        return $owner->getMorphClass() . '|' . $owner->getKey();
    }

    /**
     * @param  array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }  $context
     * @return array{
     *     affiliate_code: string|null,
     *     affiliate_name: string|null,
     *     source: string|null,
     *     medium: string|null,
     *     campaign: string|null,
     * }
     */
    private function cache(VoucherUsage $usage, array $context): array
    {
        $this->usageContextCache[$usage] = $context;

        return $context;
    }
}
