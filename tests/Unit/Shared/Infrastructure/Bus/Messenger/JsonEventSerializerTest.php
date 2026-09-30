<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Domain\Bus\Event\DeserializableEvent;
use App\Shared\Domain\Bus\Event\EventPayload;
use App\Shared\Domain\DomainEvent;
use App\Shared\Infrastructure\Bus\Messenger\JsonEventSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class JsonEventSerializerTest extends TestCase
{
    private JsonEventSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonEventSerializer(['test.thing_happened' => ConsumerThingHappened::class]);
    }

    public function test_it_encodes_the_event_as_json_named_by_its_contract(): void
    {
        $encoded = $this->serializer->encode(new Envelope($this->publisherEvent()));

        self::assertSame('test.thing_happened', $encoded['headers']['type'] ?? null);
        self::assertJsonStringEqualsJsonString(
            '{"aggregateId":"agg-1","occurredOn":"2026-09-30T10:00:00.000000+00:00","payload":{"text":"héllo","count":3}}',
            $encoded['body'],
        );
    }

    public function test_the_consumer_rebuilds_its_own_class_from_the_wire(): void
    {
        $decoded = $this->serializer->decode($this->serializer->encode(new Envelope($this->publisherEvent())));

        self::assertEquals(
            new ConsumerThingHappened('agg-1', 'héllo', new \DateTimeImmutable('2026-09-30 10:00:00')),
            $decoded->getMessage(),
        );
        self::assertSame('event.bus', $decoded->last(BusNameStamp::class)?->getBusName());
    }

    public function test_the_retry_count_survives_a_round_trip(): void
    {
        $envelope = new Envelope($this->publisherEvent(), [new RedeliveryStamp(2)]);

        $decoded = $this->serializer->decode($this->serializer->encode($envelope));

        self::assertSame(2, RedeliveryStamp::getRetryCountFromEnvelope($decoded));
    }

    public function test_an_event_nobody_consumes_cannot_be_decoded(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer->decode(['body' => '{}', 'headers' => ['type' => 'test.unknown']]);
    }

    public function test_a_payload_breaking_the_contract_cannot_be_decoded(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer->decode([
            'body' => '{"aggregateId":"agg-1","occurredOn":"2026-09-30T10:00:00+00:00","payload":{"text":42}}',
            'headers' => ['type' => 'test.thing_happened'],
        ]);
    }

    private function publisherEvent(): PublisherThingHappened
    {
        return new PublisherThingHappened('agg-1', 'héllo', 3, new \DateTimeImmutable('2026-09-30 10:00:00'));
    }
}

final readonly class PublisherThingHappened extends DomainEvent
{
    public function __construct(string $aggregateId, public string $text, public int $count, \DateTimeImmutable $occurredOn)
    {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'test.thing_happened';
    }

    public function toPrimitives(): array
    {
        return ['text' => $this->text, 'count' => $this->count];
    }
}

/**
 * The consumer only reads the part of the contract it needs.
 */
final readonly class ConsumerThingHappened extends DomainEvent implements DeserializableEvent
{
    public function __construct(string $aggregateId, public string $text, \DateTimeImmutable $occurredOn)
    {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'test.thing_happened';
    }

    public static function fromPrimitives(string $aggregateId, EventPayload $payload, \DateTimeImmutable $occurredOn): static
    {
        return new self($aggregateId, $payload->string('text'), $occurredOn);
    }

    public function toPrimitives(): array
    {
        return ['text' => $this->text];
    }
}
