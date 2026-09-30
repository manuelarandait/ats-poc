<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Domain\Bus\Event\DeserializableEvent;
use App\Shared\Domain\Bus\Event\EventPayload;
use App\Shared\Domain\DomainEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Wire format for events crossing a bounded context:
 *
 *   headers: type = event name (the contract), X-Retry-Count, X-Message-Id
 *   body:    {"aggregateId": …, "occurredOn": …, "payload": {…}}
 *
 * Plain JSON instead of PHP-serialized objects, so the publisher's class never
 * travels: each consumer rebuilds the event into the class *it* owns, looked up
 * by name in the consumers map.
 */
final readonly class JsonEventSerializer implements SerializerInterface
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * @param array<string, class-string<DomainEvent&DeserializableEvent>> $consumers event name => consumer class
     */
    public function __construct(
        #[Autowire(param: 'app.event_consumers')]
        private array $consumers,
    ) {
    }

    public function encode(Envelope $envelope): array
    {
        $event = $envelope->getMessage();

        if (!$event instanceof DomainEvent) {
            throw new \LogicException(\sprintf('Only domain events can be sent through this transport, "%s" given.', $event::class));
        }

        return [
            'body' => json_encode([
                'aggregateId' => $event->aggregateId,
                'occurredOn' => $event->occurredOn->format(self::DATE_FORMAT),
                'payload' => $event->toPrimitives(),
            ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            'headers' => [
                'type' => $event::eventName(),
                'Content-Type' => 'application/json',
                // Transport metadata Messenger relies on: retry count across
                // redeliveries and, for transports that assign it before
                // encoding, the message id used to ack/reject.
                'X-Retry-Count' => (string) RedeliveryStamp::getRetryCountFromEnvelope($envelope),
                ...(\is_scalar($id = $envelope->last(TransportMessageIdStamp::class)?->getId()) ? ['X-Message-Id' => (string) $id] : []),
            ],
        ];
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $name = $encodedEnvelope['headers']['type'] ?? null;
        $class = \is_string($name) ? ($this->consumers[$name] ?? null) : null;

        if (null === $class) {
            throw new MessageDecodingFailedException(\sprintf('No consumer registered for event "%s".', \is_string($name) ? $name : '?'));
        }

        try {
            $body = json_decode($encodedEnvelope['body'], true, 512, \JSON_THROW_ON_ERROR);
            $body = new EventPayload(\is_array($body) ? $body : []);
            $payload = $body->array('payload');

            $event = $class::fromPrimitives(
                $body->string('aggregateId'),
                new EventPayload($payload),
                new \DateTimeImmutable($body->string('occurredOn')),
            );
        } catch (\Throwable $exception) {
            throw new MessageDecodingFailedException(\sprintf('Invalid "%s" event: %s', $name, $exception->getMessage()), 0, $exception);
        }

        $headers = $encodedEnvelope['headers'] ?? [];
        $retryCount = (int) ($headers['X-Retry-Count'] ?? 0);

        return new Envelope($event, [
            new BusNameStamp('event.bus'),
            ...($retryCount > 0 ? [new RedeliveryStamp($retryCount)] : []),
            ...(isset($headers['X-Message-Id']) ? [new TransportMessageIdStamp($headers['X-Message-Id'])] : []),
        ]);
    }
}
