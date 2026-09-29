<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Event;

use App\Shared\Domain\DomainEvent;

interface EventBus
{
    /**
     * When called while a command is being handled, events are held back until
     * the command (and its transaction) has finished successfully.
     */
    public function publish(DomainEvent ...$events): void;
}
