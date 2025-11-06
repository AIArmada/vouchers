<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Vouchers\Facades\Voucher;
use InvalidArgumentException;

final class VoucherRulesFactory implements RulesFactoryInterface
{
    public const FACTORY_KEY = 'voucher';

    public function __construct(private ?RulesFactoryInterface $fallback = null) {}

    public function createRules(string $key, array $metadata = []): array
    {
        if ($key === self::FACTORY_KEY) {
            $context = $this->resolveContext($metadata);
            $code = $context['voucher_code'] ?? null;

            if (! is_string($code) || $code === '') {
                throw new InvalidArgumentException('Voucher rule requires a voucher_code metadata value.');
            }

            return [
                static function (Cart $cart, ?CartItem $item = null) use ($code): bool {
                    return Voucher::validate($code, $cart)->isValid;
                },
            ];
        }

        if ($this->fallback instanceof RulesFactoryInterface) {
            return $this->fallback->createRules($key, $metadata);
        }

        throw new InvalidArgumentException("Unknown rule factory key: {$key}");
    }

    public function canCreateRules(string $key): bool
    {
        if ($key === self::FACTORY_KEY) {
            return true;
        }

        return $this->fallback?->canCreateRules($key) ?? false;
    }

    public function getAvailableKeys(): array
    {
        $keys = $this->fallback?->getAvailableKeys() ?? [];

        if (! in_array(self::FACTORY_KEY, $keys, true)) {
            $keys[] = self::FACTORY_KEY;
        }

        return $keys;
    }

    public function getFallback(): ?RulesFactoryInterface
    {
        return $this->fallback;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function resolveContext(array $metadata): array
    {
        if (array_key_exists('context', $metadata) && is_array($metadata['context'])) {
            return $metadata['context'];
        }

        return $metadata;
    }
}
