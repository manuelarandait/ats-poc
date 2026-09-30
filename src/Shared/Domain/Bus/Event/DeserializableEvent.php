<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Event;

/**
 * Implemented by the classes a context uses to *receive* another context's
 * events. Each consumer owns its own class for the contract, so contexts
 * never share (or import) event classes.
 */
interface DeserializableEvent
{
    public static function fromPrimitives(string $aggregateId, EventPayload $payload, \DateTimeImmutable $occurredOn): static;
}
