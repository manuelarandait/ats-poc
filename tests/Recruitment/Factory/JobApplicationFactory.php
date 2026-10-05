<?php

declare(strict_types=1);

namespace App\Tests\Recruitment\Factory;

use App\Recruitment\Domain\JobApplication\AiScore;
use App\Recruitment\Domain\JobApplication\AiScreening;
use App\Recruitment\Domain\JobApplication\Candidate\Candidate;
use App\Recruitment\Domain\JobApplication\Candidate\Email;
use App\Recruitment\Domain\JobApplication\Candidate\FullName;
use App\Recruitment\Domain\JobApplication\Candidate\Phone;
use App\Recruitment\Domain\JobApplication\CvText;
use App\Recruitment\Domain\JobApplication\JobApplication;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus as Status;
use App\Recruitment\Domain\JobApplication\Notes;
use App\Recruitment\Domain\JobApplication\SkillMatch;
use App\Recruitment\Domain\JobOffer\JobOffer;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Persisted applications for integration tests. Built only through domain
 * behaviour: JobApplication::submit(), then changeStatus()/completeScreening(),
 * so a test can never seed a state the domain would forbid.
 *
 * @extends PersistentObjectFactory<JobApplication>
 */
final class JobApplicationFactory extends PersistentObjectFactory
{
    /** Shortest path through the hiring pipeline to reach each status. */
    private const array PIPELINE = [
        'received' => [],
        'in_review' => [Status::InReview],
        'interviewing' => [Status::InReview, Status::Interviewing],
        'hired' => [Status::InReview, Status::Interviewing, Status::Hired],
        'rejected' => [Status::Rejected],
    ];

    public static function class(): string
    {
        return JobApplication::class;
    }

    public function candidate(string $fullName, string $email, ?string $phone = null): static
    {
        return $this->with(['candidate' => new Candidate(FullName::fromString($fullName), Email::fromString($email), Phone::fromNullable($phone))]);
    }

    public function appliedAt(string $date): static
    {
        return $this->with(['appliedAt' => new \DateTimeImmutable($date)]);
    }

    public function forOffer(JobOffer $offer): static
    {
        return $this->with(['jobOffer' => $offer]);
    }

    public function inStatus(Status $status): static
    {
        return $this->afterInstantiate(static function (JobApplication $application) use ($status): void {
            foreach (self::PIPELINE[$status->value] as $step) {
                $application->changeStatus($step, $application->appliedAt->modify('+1 day'));
            }
        });
    }

    /**
     * @param list<SkillMatch> $skills
     */
    public function screened(int $score, string $summary = 'AI summary of the CV.', array $skills = []): static
    {
        return $this->afterInstantiate(static function (JobApplication $application) use ($score, $summary, $skills): void {
            $application->completeScreening(AiScreening::create($summary, AiScore::fromInt($score), $skills), $application->appliedAt->modify('+1 minute'));
        });
    }

    protected function defaults(): array
    {
        return [
            'id' => JobApplicationId::fromString(self::faker()->uuid()),
            'jobOffer' => JobOfferFactory::new(),
            'candidate' => new Candidate(FullName::fromString(self::faker()->name()), Email::fromString(self::faker()->unique()->safeEmail()), null),
            'cv' => CvText::fromString(self::faker()->paragraph()),
            'notes' => Notes::fromNullable(null),
            'appliedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-30 days', '-1 day')),
        ];
    }

    protected function initialize(): static
    {
        return $this
            ->instantiateWith(Instantiator::namedConstructor('submit'))
            ->afterInstantiate(static function (JobApplication $application): void {
                $application->pullDomainEvents(); // seeded data: nothing to publish
            }, -100);
    }
}
