<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Domain\Bus\Event\EventBus;
use App\Shared\Domain\DomainEvent;

final class SpyEventBus implements EventBus
{
    /** @var list<DomainEvent> */
    public private(set) array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
        }
    }
}
