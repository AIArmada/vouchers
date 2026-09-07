<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = $this->vouchersTable();

        if (! Schema::hasTable($tableName)
            || ! Schema::hasColumn($tableName, 'promotion_id')
            || ! Schema::hasColumn($tableName, 'metadata')) {
            return;
        }

        DB::table($tableName)
            ->whereNull('promotion_id')
            ->whereNotNull('metadata')
            ->chunkById(100, function (object $rows) use ($tableName): void {
                foreach ($rows as $row) {
                    $promotionId = $this->promotionIdFromMetadata($row->metadata ?? null);

                    if ($promotionId === null) {
                        continue;
                    }

                    DB::table($tableName)
                        ->where('id', $row->id)
                        ->whereNull('promotion_id')
                        ->update(['promotion_id' => $promotionId]);
                }
            });
    }

    private function vouchersTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['vouchers'] ?? $prefix . 'vouchers';
    }

    private function promotionIdFromMetadata(mixed $metadata): ?string
    {
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        if (! is_array($metadata)) {
            return null;
        }

        $promotionId = $metadata['source_promotion_id'] ?? null;

        if (! is_string($promotionId) && ! is_int($promotionId)) {
            return null;
        }

        $promotionId = mb_trim((string) $promotionId);

        return Str::isUuid($promotionId) ? $promotionId : null;
    }
};
