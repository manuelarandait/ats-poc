<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication\Event;

use App\Shared\Domain\DomainEvent;

final readonly class JobApplicationScreeningFailed extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public string $reason,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'recruitment.job_application.screening_failed';
    }

    public function toPrimitives(): array
    {
        return ['reason' => $this->reason];
    }
}
