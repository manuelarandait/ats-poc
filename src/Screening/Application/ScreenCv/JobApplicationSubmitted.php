<?php

declare(strict_types=1);

namespace App\Screening\Application\ScreenCv;

use App\Shared\Domain\Bus\Event\DeserializableEvent;
use App\Shared\Domain\Bus\Event\EventPayload;
use App\Shared\Domain\DomainEvent;

/**
 * Screening's own view of Recruitment's "recruitment.job_application.submitted"
 * event: only the fields Screening needs. Recruitment's class is never imported;
 * the event name + JSON payload is the contract between both contexts.
 */
final readonly class JobApplicationSubmitted extends DomainEvent implements DeserializableEvent
{
    public function __construct(
        string $aggregateId,
        public string $positionTitle,
        public string $positionDescription,
        public string $cv,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'recruitment.job_application.submitted';
    }

    public static function fromPrimitives(string $aggregateId, EventPayload $payload, \DateTimeImmutable $occurredOn): static
    {
        return new self(
            $aggregateId,
            $payload->string('positionTitle'),
            $payload->string('positionDescription'),
            $payload->string('cv'),
            $occurredOn,
        );
    }

    public function toPrimitives(): array
    {
        return ['positionTitle' => $this->positionTitle, 'positionDescription' => $this->positionDescription, 'cv' => $this->cv];
    }
}
