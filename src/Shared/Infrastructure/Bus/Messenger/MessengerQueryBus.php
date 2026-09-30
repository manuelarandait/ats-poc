<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Domain\Bus\Query\Query;
use App\Shared\Domain\Bus\Query\QueryBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerQueryBus implements QueryBus
{
    use HandleTrait;

    public function __construct(
        #[Autowire(service: 'query.bus')]
        MessageBusInterface $bus,
    ) {
        $this->messageBus = $bus;
    }

    public function ask(Query $query): mixed
    {
        try {
            // Exactly one handler, synchronous, returns its result.
            return $this->handle($query);
        } catch (HandlerFailedException $exception) {
            throw current($exception->getWrappedExceptions()) ?: $exception;
        }
    }
}
