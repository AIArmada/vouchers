<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Services\VoucherValidator;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateVoucherCode
{
    use AsAction;

    public function __construct(
        private readonly VoucherValidator $validator
    ) {}

    public function handle(string $code, mixed $cart): VoucherValidationResult
    {
        return $this->validator->validate($code, $cart);
    }
}
