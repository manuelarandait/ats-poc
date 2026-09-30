<?php

declare(strict_types=1);

namespace App\Screening\Domain\Event;

use App\Shared\Domain\DomainEvent;

/**
 * Public event: the CV could not be screened, even after retrying.
 */
final readonly class CvScreeningFailed extends DomainEvent
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
        return 'screening.cv_screening_failed';
    }

    public function toPrimitives(): array
    {
        return ['reason' => $this->reason];
    }
}
