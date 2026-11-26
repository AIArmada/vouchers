<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Affiliates\Features\Reporting\Api\VoucherReportingReader;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class AffiliateReportingContextResolver
{
    /**
     * @var array<string, string|null>
     */
    private array $orderNumberCache = [];

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
        $voucherCode = $this->resolveVoucherCode($usage);
        $reader = $this->reader();

        if ($voucherCode === null || $reader === null || ! $reader->available()) {
            return $this->emptyContext();
        }

        $orderReference = $this->resolveOrderReference($usage);
        $cacheKey = serialize([$this->scopeCacheKey(), $voucherCode, $orderReference]);

        return $this->lookupCache[$cacheKey] ??= $reader->resolve($voucherCode, $orderReference)?->toArray() ?? $this->emptyContext();
    }

    public function supportsAffiliateReporting(): bool
    {
        return $this->reader()?->available() ?? false;
    }

    /**
     * @return array<string, string>
     */
    public function affiliateOptions(): array
    {
        return $this->reader()?->affiliateOptions() ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function sourceOptions(): array
    {
        return $this->reader()?->attributionOptions('source') ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function mediumOptions(): array
    {
        return $this->reader()?->attributionOptions('medium') ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function campaignOptions(): array
    {
        return $this->reader()?->attributionOptions('campaign') ?? [];
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

    private function reader(): ?VoucherReportingReader
    {
        return interface_exists(VoucherReportingReader::class) && app()->bound(VoucherReportingReader::class)
            ? app(VoucherReportingReader::class)
            : null;
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

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = mb_trim($value);

        return $normalized !== '' ? $normalized : null;
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
        $cacheKey = serialize([$this->scopeCacheKey(), $orderId]);

        if (array_key_exists($cacheKey, $this->orderNumberCache)) {
            return $this->orderNumberCache[$cacheKey];
        }

        if (! $this->hasTableFor(Order::class)) {
            $this->orderNumberCache[$cacheKey] = null;

            return null;
        }

        $order = Order::query()
            ->select(['id', 'order_number'])
            ->find($orderId);

        $orderNumber = $order instanceof Model
            ? $this->normalizeString($order->getAttribute('order_number'))
            : null;

        $this->orderNumberCache[$cacheKey] = $orderNumber;

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

            return $model instanceof Model && $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable());
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
}
