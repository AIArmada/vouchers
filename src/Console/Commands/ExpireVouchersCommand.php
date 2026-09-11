<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Vouchers\Actions\ExpireVoucher;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Paused;
use AIArmada\Vouchers\States\VoucherStatus;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class ExpireVouchersCommand extends Command
{
    protected $signature = 'vouchers:expire {--dry-run : Report expired vouchers without changing status}';

    protected $description = 'Transition vouchers past their expiry time to expired';

    public function handle(ExpireVoucher $expireVoucher): int
    {
        $now = CarbonImmutable::now();
        $dryRun = (bool) $this->option('dry-run');
        $processed = 0;

        Voucher::query()
            ->withoutOwnerScope()
            ->whereIn('status', [
                VoucherStatus::normalize(Active::class),
                VoucherStatus::normalize(Paused::class),
            ])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->chunkById(100, function ($vouchers) use ($dryRun, $expireVoucher, &$processed): void {
                foreach ($vouchers as $voucher) {
                    $processed++;

                    if ($dryRun) {
                        $this->line("Would expire {$voucher->code}");

                        continue;
                    }

                    try {
                        $owner = OwnerTupleParser::fromTypeAndId(
                            $voucher->owner_type,
                            $voucher->owner_id,
                        )->toOwnerModel();

                        OwnerContext::withOwner($owner, function () use ($expireVoucher, $voucher): void {
                            $expireVoucher->handle($voucher->code);
                        });
                    } catch (Throwable $exception) {
                        $this->warn("Unable to expire {$voucher->code}: {$exception->getMessage()}");
                    }
                }
            });

        $verb = $dryRun ? 'Found' : 'Expired';
        $this->info("{$verb} {$processed} voucher(s).");

        return self::SUCCESS;
    }
}
