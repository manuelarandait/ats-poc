<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;

/**
 * Runs a real Messenger worker over the in-memory "async" transport until it
 * is idle, with the app's event dispatcher: retries, the failure transport and
 * our own worker listeners behave exactly as in the worker container.
 */
trait ConsumesAsyncMessages
{
    private const int MAX_MESSAGES_PER_RUN = 50;

    private function consumeAsyncMessages(): void
    {
        $container = self::getContainer();
        $transport = $container->get('messenger.transport.async');
        $bus = $container->get('messenger.routable_message_bus');
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        $worker = new Worker(['async' => $transport], $bus, $dispatcher);
        $handled = 0;
        $stopWhenIdle = static function (WorkerRunningEvent $event) use ($worker, &$handled): void {
            if ($event->isWorkerIdle() || ++$handled >= self::MAX_MESSAGES_PER_RUN) {
                $worker->stop();
            }
        };

        $dispatcher->addListener(WorkerRunningEvent::class, $stopWhenIdle);

        try {
            $worker->run(['sleep' => 0]);
        } finally {
            $dispatcher->removeListener(WorkerRunningEvent::class, $stopWhenIdle);
        }
    }
}
