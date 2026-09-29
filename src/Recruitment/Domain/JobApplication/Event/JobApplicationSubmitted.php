<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication\Event;

use App\Shared\Domain\DomainEvent;

final readonly class JobApplicationSubmitted extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public string $jobOfferId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'recruitment.job_application.submitted';
    }
}
