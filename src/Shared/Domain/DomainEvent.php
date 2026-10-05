<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Immutable fact that happened in the domain. Carries primitives only so it can
 * travel through a message broker as JSON.
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
     * It is the public contract on the wire: other contexts rely on it.
     */
    abstract public static function eventName(): string;

    /**
     * Event-specific data (aggregate id and date travel separately): plain
     * values, or a list of flat records (e.g. a skill-by-skill breakdown).
     *
     * @return array<string, scalar|list<array<string, scalar|null>>|null>
     */
    abstract public function toPrimitives(): array;
}
