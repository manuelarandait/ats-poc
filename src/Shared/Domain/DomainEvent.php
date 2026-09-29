<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Immutable fact that happened in the domain. Carries primitives only so it can
 * travel through a message broker unchanged.
 */
abstract readonly class DomainEvent
{
    public function __construct(
        public string $aggregateId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    /**
     * Stable, context-qualified name, e.g. "recruitment.job_application.submitted".
     */
    abstract public static function eventName(): string;
}
