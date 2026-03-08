<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\States;

use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @method Voucher getModel()
 */
abstract class VoucherStatus extends State
{
    abstract public function label(): string;

    abstract public function description(): string;

    public function canBeUsed(): bool
    {
        return false;
    }

    public function isActive(): bool
    {
        return false;
    }

    public function isPaused(): bool
    {
        return false;
    }

    public function isExpired(): bool
    {
        return false;
    }

    public function isDepleted(): bool
    {
        return false;
    }

    public static function normalize(string | VoucherStatus $status): string
    {
        if ($status instanceof VoucherStatus) {
            return $status->getValue();
        }

        if (class_exists($status) && is_subclass_of($status, VoucherStatus::class)) {
            return $status::getMorphClass();
        }

        return $status;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?Model $model = null): array
    {
        $model ??= new Voucher;

        $options = [];

        /** @var class-string<VoucherStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            $options[$state->getValue()] = $state->label();
        }

        return $options;
    }

    public static function labelFor(string | VoucherStatus $status, ?Model $model = null): string
    {
        if ($status instanceof VoucherStatus) {
            return $status->label();
        }

        $model ??= new Voucher;
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->label();
    }

    public static function descriptionFor(string | VoucherStatus $status, ?Model $model = null): string
    {
        if ($status instanceof VoucherStatus) {
            return $status->description();
        }

        $model ??= new Voucher;
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->description();
    }

    public static function fromString(string | VoucherStatus $status, ?Model $model = null): VoucherStatus
    {
        if ($status instanceof VoucherStatus) {
            return $status;
        }

        $model ??= new Voucher;
        $stateClass = self::resolveStateClassFor($status, $model);

        return new $stateClass($model);
    }

    /**
     * @return class-string<VoucherStatus>
     */
    public static function resolveStateClassFor(string | VoucherStatus $status, ?Model $model = null): string
    {
        if ($status instanceof VoucherStatus) {
            return $status::class;
        }

        if (class_exists($status) && is_subclass_of($status, VoucherStatus::class)) {
            return $status;
        }

        $model ??= new Voucher;

        /** @var class-string<VoucherStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            if ($state->getValue() === $status) {
                return $stateClass;
            }
        }

        return Active::class;
    }

    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(Active::class)
            ->allowTransition(Active::class, Paused::class)
            ->allowTransition(Active::class, Expired::class)
            ->allowTransition(Active::class, Depleted::class)
            ->allowTransition(Paused::class, Active::class)
            ->allowTransition(Paused::class, Expired::class)
            ->allowTransition(Paused::class, Depleted::class);
    }
}
