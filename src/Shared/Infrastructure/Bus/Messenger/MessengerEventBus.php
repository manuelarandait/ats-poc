<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Domain\Bus\Event\EventBus;
use App\Shared\Domain\DomainEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class MessengerEventBus implements EventBus
{
    public function __construct(
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $bus,
    ) {
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            // Held back until the current command (and its DB transaction) succeeds:
            // nobody reacts to data that could still be rolled back.
            $this->bus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
