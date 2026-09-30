<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication\Event;

use App\Shared\Domain\DomainEvent;

/**
 * Public event: crosses to other contexts. It carries everything needed to
 * screen the CV (CV text + position), so consumers don't have to query us.
 */
final readonly class JobApplicationSubmitted extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public string $jobOfferId,
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

    public function toPrimitives(): array
    {
        return [
            'jobOfferId' => $this->jobOfferId,
            'positionTitle' => $this->positionTitle,
            'positionDescription' => $this->positionDescription,
            'cv' => $this->cv,
        ];
    }
}
