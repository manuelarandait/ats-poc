<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Fixtures;

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
use App\Recruitment\Domain\JobOffer\JobOffer;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;

/**
 * Demo applications in varied states so the list, filters and detail page
 * have something to show. Built only through domain behaviour (submit,
 * changeStatus, completeScreening…), so no impossible state can be seeded.
 */
final class JobApplicationFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function getDependencies(): array
    {
        return [JobOfferFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->applications() as $i => $data) {
            $appliedAt = $this->clock->now()->modify(\sprintf('-%d minutes', $data['minutesAgo']));
            $screenedAt = $appliedAt->modify('+1 minute');

            $application = JobApplication::submit(
                JobApplicationId::fromString(\sprintf('0192f5a1-0000-7000-8000-%012d', $i + 1)),
                $this->offer($manager, $data['offer']),
                new Candidate(FullName::fromString($data['name']), Email::fromString($data['email']), Phone::fromNullable($data['phone'])),
                CvText::fromString($data['cv']),
                Notes::fromNullable($data['notes']),
                $appliedAt,
            );

            if (null === $data['screening']) {
                $application->failScreening('LLM provider timeout', $screenedAt);
            } else {
                [$score, $summary] = $data['screening'];
                $application->completeScreening(AiScreening::create($summary, AiScore::fromInt($score)), $screenedAt);
            }

            foreach ($data['pipeline'] as $step => $status) {
                $application->changeStatus($status, $screenedAt->modify(\sprintf('+%d hours', $step + 1)));
            }

            $application->pullDomainEvents(); // seeded data: nothing to notify
            $manager->persist($application);
        }

        $manager->flush();
    }

    /**
     * find() instead of fixture references: getReference() hands out lazy
     * proxies, and Doctrine can't initialise a proxy whose readonly id is a
     * value object (it sees the re-hydrated id as a change).
     */
    private function offer(ObjectManager $manager, string $id): JobOffer
    {
        return $manager->find(JobOffer::class, JobOfferId::fromString($id))
            ?? throw new \LogicException(\sprintf('Job offer fixture "%s" not loaded.', $id));
    }

    /**
     * @return list<array{name: string, email: string, phone: ?string, offer: string, minutesAgo: int, notes: ?string, cv: string, screening: array{int, string}|null, pipeline: list<Status>}>
     */
    private function applications(): array
    {
        return [
            [
                'name' => 'Lucía Fernández', 'email' => 'lucia.fernandez@example.com', 'phone' => '+34 611 222 333',
                'offer' => JobOfferFixtures::SENIOR_PHP, 'minutesAgo' => 120, 'notes' => 'Available from November.',
                'cv' => "Backend engineer, 7 years with PHP.\nSymfony 5–7, Doctrine, DDD and hexagonal architecture at a fintech.\nRabbitMQ consumers, PostgreSQL tuning, PHPUnit, Docker, GitHub Actions.",
                'screening' => [88, 'Senior PHP/Symfony engineer with 7 years of experience, solid DDD, hexagonal and messaging background (RabbitMQ). Strong match for the backend role.'],
                'pipeline' => [Status::InReview],
            ],
            [
                'name' => 'Marco Rossi', 'email' => 'marco.rossi@example.com', 'phone' => null,
                'offer' => JobOfferFixtures::SENIOR_PHP, 'minutesAgo' => 300, 'notes' => null,
                'cv' => "Full-stack developer, 4 years.\nLaravel and Vue.js for e-commerce sites, MySQL, some Docker.\nLooking to grow into backend architecture.",
                'screening' => [54, 'Full-stack developer with 4 years of Laravel and Vue.js. Relevant PHP experience but no Symfony, messaging or DDD exposure yet.'],
                'pipeline' => [],
            ],
            [
                'name' => 'Aisha Khan', 'email' => 'aisha.khan@example.com', 'phone' => '+44 7700 900123',
                'offer' => JobOfferFixtures::FRONTEND, 'minutesAgo' => 1_440, 'notes' => 'Portfolio: aisha.dev',
                'cv' => "Frontend engineer, 5 years.\nReact, TypeScript, Next.js, Tailwind, design systems with Storybook.\nAccessibility champion (WCAG 2.2), Jest, Testing Library, Playwright.",
                'screening' => [91, 'Frontend engineer with 5 years of React and TypeScript, design-system and accessibility expertise, full testing stack. Excellent fit.'],
                'pipeline' => [Status::InReview, Status::Interviewing],
            ],
            [
                'name' => 'Tomás Ruiz', 'email' => 'tomas.ruiz@example.com', 'phone' => '600 123 456',
                'offer' => JobOfferFixtures::DATA_ENGINEER, 'minutesAgo' => 1_680, 'notes' => null,
                'cv' => "Data engineer, 3 years.\nPython, Airflow, advanced SQL, dbt on Snowflake.\nSome AWS (S3, Glue).",
                'screening' => [76, 'Data engineer with 3 years of Python, Airflow and dbt. Covers the core requirements; limited cloud and no streaming experience.'],
                'pipeline' => [],
            ],
            [
                'name' => 'Emma Johansson', 'email' => 'emma.johansson@example.com', 'phone' => null,
                'offer' => JobOfferFixtures::FRONTEND, 'minutesAgo' => 2_880, 'notes' => 'Open to switching to frontend.',
                'cv' => "Backend developer, 6 years.\nJava, Spring Boot, microservices, Kafka.\nBasic HTML/CSS.",
                'screening' => [23, 'Experienced Java backend developer with little frontend experience: no React or TypeScript. Low match for this role.'],
                'pipeline' => [Status::Rejected],
            ],
            [
                'name' => 'Daniel Okafor', 'email' => 'daniel.okafor@example.com', 'phone' => '+34 622 333 444',
                'offer' => JobOfferFixtures::SENIOR_PHP, 'minutesAgo' => 8_640, 'notes' => null,
                'cv' => "Staff engineer, 10 years with PHP.\nSymfony, API Platform, CQRS and event sourcing, RabbitMQ and Kafka.\nLed migrations to hexagonal architecture; mentoring and CI/CD.",
                'screening' => [95, 'Staff-level PHP engineer, 10 years with Symfony, CQRS and event-driven systems, led hexagonal migrations. Outstanding match.'],
                'pipeline' => [Status::InReview, Status::Interviewing, Status::Hired],
            ],
            [
                'name' => 'Sofía Martín', 'email' => 'sofia.martin@example.com', 'phone' => null,
                'offer' => JobOfferFixtures::DATA_ENGINEER, 'minutesAgo' => 180, 'notes' => null,
                'cv' => "Analytics engineer, 2 years.\nSQL, dbt, Looker. Learning Python and Airflow.",
                'screening' => null,
                'pipeline' => [],
            ],
            [
                'name' => 'Li Wei', 'email' => 'li.wei@example.com', 'phone' => '+86 138 0013 8000',
                'offer' => JobOfferFixtures::DATA_ENGINEER, 'minutesAgo' => 4_320, 'notes' => 'Currently in Madrid.',
                'cv' => "Software engineer, 4 years.\nPython services, PostgreSQL, Kafka streaming, Spark jobs on AWS EMR.\nNo Airflow yet.",
                'screening' => [67, 'Python engineer with streaming (Kafka) and Spark experience on AWS; missing Airflow and dbt. Reasonable match.'],
                'pipeline' => [Status::InReview],
            ],
        ];
    }
}
