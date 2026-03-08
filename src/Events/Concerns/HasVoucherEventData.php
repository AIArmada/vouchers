<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Events\Concerns;

use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Trait providing common event data functionality for voucher events.
 *
 * Implements VoucherEventInterface methods for event sourcing and analytics.
 * Use in voucher event classes that need event store integration.
 */
trait HasVoucherEventData
{
    /**
     * Unique event identifier.
     */
    protected string $eventId;

    /**
     * Timestamp when event occurred.
     */
    protected DateTimeImmutable $occurredAt;

    /**
     * Whether this event should be persisted.
     */
    protected bool $persist = true;

    /**
     * Get the event type name.
     */
    abstract public function getEventType(): string;

    /**
     * Get the unique event identifier.
     */
    public function getEventId(): string
    {
        return $this->eventId;
    }

    /**
     * Get when the event occurred.
     */
    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Get the voucher code from the voucher data.
     */
    public function getVoucherCode(): string
    {
        return $this->voucher->code;
    }

    /**
     * Get the voucher ID from the voucher data.
     */
    public function getVoucherId(): ?string
    {
        return $this->voucher->id;
    }

    /**
     * Get the cart identifier.
     */
    public function getCartIdentifier(): ?string
    {
        return $this->cart->getIdentifier();
    }

    /**
     * Get the cart instance name.
     */
    public function getCartInstance(): ?string
    {
        return $this->cart->getInstanceName();
    }

    /**
     * Get the discount amount in cents (if applicable).
     */
    public function getDiscountAmountCents(): ?int
    {
        // VoucherData stores value as a float (can be cents or percentage depending on type)
        return (int) ($this->voucher->value * 100);
    }

    /**
     * Determine if this event should be persisted.
     */
    public function shouldPersist(): bool
    {
        return $this->persist;
    }

    /**
     * Set whether this event should be persisted.
     */
    public function withPersistence(bool $persist): static
    {
        $clone = clone $this;
        $clone->persist = $persist;

        return $clone;
    }

    /**
     * Create an event without persistence (for replays, testing).
     */
    public function withoutPersistence(): static
    {
        return $this->withPersistence(false);
    }

    /**
     * Convert event to a storable payload.
     *
     * @return array<string, mixed>
     */
    public function toEventPayload(): array
    {
        return [
            'event_type' => $this->getEventType(),
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format('c'),
            'voucher_code' => $this->getVoucherCode(),
            'voucher_id' => $this->getVoucherId(),
            'cart_identifier' => $this->getCartIdentifier(),
            'cart_instance' => $this->getCartInstance(),
            'discount_cents' => $this->getDiscountAmountCents(),
        ];
    }

    /**
     * Get event metadata for storage.
     *
     * @return array<string, mixed>
     */
    public function getEventMetadata(): array
    {
        return [
            'source' => 'vouchers',
            'version' => '1.0',
            'timestamp' => $this->occurredAt->format('c'),
        ];
    }

    /**
     * Initialize event data. Call in constructor.
     */
    protected function initializeEventData(): void
    {
        $this->eventId = (string) Str::uuid();
        $this->occurredAt = new DateTimeImmutable;
    }
}
