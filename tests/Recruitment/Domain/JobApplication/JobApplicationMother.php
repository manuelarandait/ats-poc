<?php

declare(strict_types=1);

namespace App\Tests\Recruitment\Domain\JobApplication;

use App\Recruitment\Domain\JobApplication\AiScore;
use App\Recruitment\Domain\JobApplication\AiScreening;
use App\Recruitment\Domain\JobApplication\Candidate\Candidate;
use App\Recruitment\Domain\JobApplication\CvText;
use App\Recruitment\Domain\JobApplication\JobApplication;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\Notes;
use App\Recruitment\Domain\JobApplication\SkillMatch;
use App\Recruitment\Domain\JobOffer\JobOffer;
use App\Tests\Recruitment\Domain\JobOffer\JobOfferMother;
use App\Tests\Shared\Domain\MotherCreator;

final class JobApplicationMother
{
    public static function submitted(
        ?JobApplicationId $id = null,
        ?JobOffer $jobOffer = null,
        ?Candidate $candidate = null,
        ?string $cv = null,
        ?string $notes = null,
        ?\DateTimeImmutable $appliedAt = null,
    ): JobApplication {
        return JobApplication::submit(
            $id ?? self::id(),
            $jobOffer ?? JobOfferMother::create(),
            $candidate ?? CandidateMother::create(),
            CvText::fromString($cv ?? self::cv()),
            Notes::fromNullable($notes),
            $appliedAt ?? new \DateTimeImmutable('2026-09-01 10:00:00'),
        );
    }

    /**
     * An application whose creation events were already published, as it
     * would come back from the repository.
     */
    public static function persisted(?JobApplicationId $id = null): JobApplication
    {
        $application = self::submitted(id: $id);
        $application->pullDomainEvents();

        return $application;
    }

    /**
     * @param list<SkillMatch> $skills
     */
    public static function aiScreening(int $score = 80, string $summary = 'Backend engineer with 6 years of PHP.', array $skills = []): AiScreening
    {
        return AiScreening::create($summary, AiScore::fromInt($score), $skills);
    }

    public static function id(): JobApplicationId
    {
        return JobApplicationId::fromString(MotherCreator::faker()->uuid());
    }

    private static function cv(): string
    {
        return <<<'CV'
            Jane Doe — Backend Engineer
            6 years building PHP/Symfony applications. DDD, hexagonal architecture,
            RabbitMQ, PostgreSQL, Docker. Led the migration of a monolith to modules.
            CV;
    }
}
