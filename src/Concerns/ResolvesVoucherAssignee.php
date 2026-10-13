<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Concerns;

use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Models\Voucher;

/**
 * Resolves the generic recipient assignee marker.
 *
 * A voucher carrying `metadata['assignee'] = ['type' => morph-class,
 * 'id' => key]` is bound to that single recipient. An absent marker means
 * the voucher is unassigned; a present malformed marker fails closed.
 */
trait ResolvesVoucherAssignee
{
    /**
     * @return array{type: string, id: string}|null
     */
    protected function resolveAssignee(Voucher $voucher): ?array
    {
        $metadata = $voucher->getAttribute('metadata');

        if (! is_array($metadata)) {
            return null;
        }

        if (! array_key_exists('assignee', $metadata)) {
            return null;
        }

        $assignee = $metadata['assignee'];

        if (! is_array($assignee)) {
            throw InvalidVoucherException::invalidAssignee($voucher->code);
        }

        $type = $assignee['type'] ?? null;
        $id = $assignee['id'] ?? null;

        if (! is_string($type) || $type === '' || (! is_string($id) && ! is_int($id)) || (string) $id === '') {
            throw InvalidVoucherException::invalidAssignee($voucher->code);
        }

        return ['type' => $type, 'id' => (string) $id];
    }
}
