<?php

declare(strict_types=1);

namespace App\Recruitment\Application\CompleteJobApplicationScreening;

use App\Shared\Domain\Bus\Event\DeserializableEvent;
use App\Shared\Domain\Bus\Event\EventPayload;
use App\Shared\Domain\Bus\Event\InvalidEventPayload;
use App\Shared\Domain\DomainEvent;

/**
 * Recruitment's own view of Screening's "screening.cv_screened" event.
 */
final readonly class CvScreened extends DomainEvent implements DeserializableEvent
{
    /**
     * @param list<array{skill: string, required: bool, matched: bool}> $skills
     */
    public function __construct(
        string $aggregateId,
        public string $summary,
        public int $score,
        public array $skills,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'screening.cv_screened';
    }

    public static function fromPrimitives(string $aggregateId, EventPayload $payload, \DateTimeImmutable $occurredOn): static
    {
        // Events published before "skills" existed still read as "no skill breakdown".
        return new self($aggregateId, $payload->string('summary'), $payload->int('score'), self::skills($payload->array('skills', [])), $occurredOn);
    }

    /**
     * @param array<array-key, mixed> $skills
     *
     * @return list<array{skill: string, required: bool, matched: bool}>
     */
    private static function skills(array $skills): array
    {
        return array_values(array_map(static function (mixed $skill): array {
            if (!\is_array($skill) || !\is_string($skill['skill'] ?? null) || !\is_bool($skill['required'] ?? null) || !\is_bool($skill['matched'] ?? null)) {
                throw InvalidEventPayload::field('skills', 'list of {skill, required, matched}');
            }

            return ['skill' => $skill['skill'], 'required' => $skill['required'], 'matched' => $skill['matched']];
        }, $skills));
    }

    public function toPrimitives(): array
    {
        return ['summary' => $this->summary, 'score' => $this->score, 'skills' => $this->skills];
    }
}
