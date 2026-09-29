<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Aggregates record what happened; they never dispatch. Whoever persists the
 * aggregate pulls the events and publishes them once the transaction commits.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    /**
     * @return list<DomainEvent>
     */
    final public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    final protected function record(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
}
