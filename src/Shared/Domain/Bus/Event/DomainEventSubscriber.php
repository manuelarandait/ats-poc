<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Event;

/**
 * Marker for domain event subscribers (one public __invoke per subscriber).
 */
interface DomainEventSubscriber
{
}
