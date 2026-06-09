<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Filament\Exports;

use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Support\AffiliateReportingContextResolver;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

final class VoucherUsageExporter extends Exporter
{
    protected static ?string $model = VoucherUsage::class;

    /**
     * @param  Builder<VoucherUsage>  $query
     * @return Builder<VoucherUsage>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['voucher', 'redeemedBy']);
    }

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        $affiliateReporting = app(AffiliateReportingContextResolver::class);

        return [
            ExportColumn::make('id')
                ->label('Usage ID'),

            ExportColumn::make('used_at')
                ->label('Redeemed At'),

            ExportColumn::make('voucher_code')
                ->label('Voucher Code')
                ->state(fn (VoucherUsage $record): string => (string) ($record->voucher?->code ?? '')),

            ExportColumn::make('channel')
                ->label('Channel'),

            ExportColumn::make('discount_amount')
                ->label('Discount (minor)'),

            ExportColumn::make('currency')
                ->label('Currency'),

            ExportColumn::make('user_identifier')
                ->label('User'),

            ExportColumn::make('order_id')
                ->label('Order ID')
                ->state(fn (VoucherUsage $record): ?string => $affiliateReporting->orderId($record)),

            ExportColumn::make('order_number')
                ->label('Order Number')
                ->state(fn (VoucherUsage $record): ?string => $affiliateReporting->orderNumber($record)),

            ExportColumn::make('affiliate_code')
                ->label('Affiliate Code')
                ->state(fn (VoucherUsage $record): ?string => $affiliateReporting->resolve($record)['affiliate_code']),

            ExportColumn::make('affiliate_name')
                ->label('Affiliate Name')
                ->state(fn (VoucherUsage $record): ?string => $affiliateReporting->resolve($record)['affiliate_name']),

            ExportColumn::make('affiliate_source')
                ->label('Source')
                ->state(fn (VoucherUsage $record): ?string => $affiliateReporting->resolve($record)['source']),

            ExportColumn::make('affiliate_medium')
                ->label('Medium')
                ->state(fn (VoucherUsage $record): ?string => $affiliateReporting->resolve($record)['medium']),

            ExportColumn::make('affiliate_campaign')
                ->label('Campaign')
                ->state(fn (VoucherUsage $record): ?string => $affiliateReporting->resolve($record)['campaign']),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your voucher usage export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
