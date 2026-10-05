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
use App\Recruitment\Domain\JobApplication\SkillMatch;
use App\Recruitment\Domain\JobOffer\JobOffer;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Psr\Clock\ClockInterface;

/**
 * Demo applications in varied states so the list, filters, pagination and
 * detail page have something to show: eight hand-written ones plus a batch
 * generated with a fixed Faker seed (same data on every load). All are built
 * only through domain behaviour (submit, changeStatus, completeScreening…),
 * so no impossible state can be seeded.
 */
final class JobApplicationFixtures extends Fixture implements DependentFixtureInterface
{
    private const int GENERATED = 24;
    private const int SEED = 20261001;

    /**
     * What the mock LLM finds each offer asking for (required, then nice to
     * have). The pre-computed AI results mirror it: the skill breakdown is
     * built against these, and the score is coverage (80 %, nice-to-have
     * weighing half) + seniority (20 %).
     */
    private const array ASKED = [
        JobOfferFixtures::SENIOR_PHP => [
            'required' => ['PHP', 'Symfony', 'Doctrine', 'DDD', 'Hexagonal architecture', 'RabbitMQ', 'PostgreSQL', 'Docker', 'PHPUnit'],
            'niceToHave' => ['CQRS', 'Event-driven design', 'API Platform', 'CI/CD'],
        ],
        JobOfferFixtures::FRONTEND => [
            'required' => ['React', 'TypeScript', 'Tailwind', 'Accessibility', 'Jest', 'Testing Library', 'Playwright'],
            'niceToHave' => ['Next.js', 'Storybook'],
        ],
        JobOfferFixtures::DATA_ENGINEER => [
            'required' => ['Python', 'Airflow', 'dbt', 'SQL', 'AWS'],
            'niceToHave' => ['Kafka', 'Spark'],
        ],
    ];

    /** What a generated candidate for each offer may list (asked skills plus a few others). */
    private const array PROFILES = [
        JobOfferFixtures::SENIOR_PHP => [
            'roles' => ['Backend engineer', 'PHP developer', 'Software engineer'],
            'skills' => ['PHP', 'Symfony', 'Doctrine', 'DDD', 'Hexagonal architecture', 'RabbitMQ', 'PostgreSQL', 'Docker', 'PHPUnit', 'CQRS', 'API Platform', 'CI/CD', 'Redis'],
        ],
        JobOfferFixtures::FRONTEND => [
            'roles' => ['Frontend engineer', 'UI developer', 'Web developer'],
            'skills' => ['React', 'TypeScript', 'Tailwind', 'CSS', 'Jest', 'Testing Library', 'Playwright', 'Next.js', 'Storybook', 'Accessibility'],
        ],
        JobOfferFixtures::DATA_ENGINEER => [
            'roles' => ['Data engineer', 'Analytics engineer', 'Python developer'],
            'skills' => ['Python', 'Airflow', 'dbt', 'SQL', 'AWS', 'Spark', 'Kafka', 'Snowflake', 'data modelling'],
        ],
    ];

    private const array HIGHLIGHTS = [
        'Grew a team from 3 to 8 engineers.',
        'Worked at a fintech scale-up.',
        'Open-source contributor.',
        'Remote-first for the last four years.',
        'Mentor at a coding bootcamp.',
        'Led a legacy migration end to end.',
        'Speaker at local meetups.',
    ];

    private const array NOTES = ['Available immediately.', 'Two weeks notice.', 'Open to relocation.', 'Prefers hybrid work.'];

    /** Hiring pipelines a generated application may have gone through (duplicates = more likely). */
    private const array PIPELINES = [
        [], [], [], [],
        [Status::InReview], [Status::InReview], [Status::InReview],
        [Status::InReview, Status::Interviewing], [Status::InReview, Status::Interviewing],
        [Status::Rejected], [Status::Rejected],
        [Status::InReview, Status::Interviewing, Status::Hired],
    ];

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function getDependencies(): array
    {
        return [JobOfferFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        foreach ([...$this->applications(), ...$this->generatedApplications()] as $i => $data) {
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
                $application->completeScreening($this->screening($data['offer'], $data['screening']), $screenedAt);
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
     * @param array{summary: string, years: int, skills: list<string>} $screening skills: what the CV shows
     */
    private function screening(string $offer, array $screening): AiScreening
    {
        ['required' => $required, 'niceToHave' => $niceToHave] = self::ASKED[$offer];
        $has = static fn (string $skill): bool => \in_array($skill, $screening['skills'], true);

        $asked = \count($required) + 0.5 * \count($niceToHave);
        $covered = \count(array_filter($required, $has)) + 0.5 * \count(array_filter($niceToHave, $has));
        $score = (int) round(80 * $covered / $asked + 20 * min($screening['years'], 8) / 8);

        return AiScreening::create($screening['summary'], AiScore::fromInt($score), [
            ...array_map(static fn (string $skill): SkillMatch => SkillMatch::create($skill, true, $has($skill)), $required),
            ...array_map(static fn (string $skill): SkillMatch => SkillMatch::create($skill, false, $has($skill)), $niceToHave),
        ]);
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
     * @return list<array{name: string, email: string, phone: ?string, offer: string, minutesAgo: int, notes: ?string, cv: string, screening: array{summary: string, years: int, skills: list<string>}|null, pipeline: list<Status>}>
     */
    private function generatedApplications(): array
    {
        $faker = Factory::create('en_US');
        $faker->seed(self::SEED);
        $offers = array_keys(self::PROFILES);
        $applications = [];

        for ($i = 0; $i < self::GENERATED; ++$i) {
            $offer = $offers[$i % \count($offers)];
            ['roles' => $roles, 'skills' => $pool] = self::PROFILES[$offer];

            $role = $roles[$faker->numberBetween(0, \count($roles) - 1)];
            $years = $faker->numberBetween(1, 12);
            $skills = array_values(array_filter($pool, static fn (): bool => $faker->boolean(55))) ?: [$pool[0]];

            // Plain first + last name (Faker's name() adds "Mrs.", "Jr.", "DDS"…) and an email derived from it.
            [$firstName, $lastName] = [$faker->firstName(), $faker->lastName()];
            $emailName = strtolower((string) preg_replace('/[^a-z]+/i', '', $firstName).'.'.(string) preg_replace('/[^a-z]+/i', '', $lastName));

            $applications[] = [
                'name' => $firstName.' '.$lastName,
                'email' => \sprintf('%s.%d@example.com', $emailName, $i + 1),
                'phone' => $faker->boolean(60) ? $faker->e164PhoneNumber() : null,
                'offer' => $offer,
                'minutesAgo' => $faker->numberBetween(60 * 24, 60 * 24 * 30), // 1–30 days ago
                'notes' => $faker->boolean(25) ? self::NOTES[$faker->numberBetween(0, \count(self::NOTES) - 1)] : null,
                'cv' => \sprintf("%s, %d years of experience.\nSkills: %s.\n%s", $role, $years, implode(', ', $skills), self::HIGHLIGHTS[$faker->numberBetween(0, \count(self::HIGHLIGHTS) - 1)]),
                // One in twelve: the AI analysis failed after its retries.
                'screening' => 5 === $i % 12 ? null : [
                    'summary' => \sprintf('%s with %d years of experience. Main skills: %s.', $role, $years, implode(', ', \array_slice($skills, 0, 5)).(\count($skills) > 5 ? \sprintf(' (+%d more)', \count($skills) - 5) : '')),
                    'years' => $years,
                    'skills' => $skills,
                ],
                'pipeline' => self::PIPELINES[$faker->numberBetween(0, \count(self::PIPELINES) - 1)],
            ];
        }

        return $applications;
    }

    /**
     * @return list<array{name: string, email: string, phone: ?string, offer: string, minutesAgo: int, notes: ?string, cv: string, screening: array{summary: string, years: int, skills: list<string>}|null, pipeline: list<Status>}>
     */
    private function applications(): array
    {
        return [
            [
                'name' => 'Lucía Fernández', 'email' => 'lucia.fernandez@example.com', 'phone' => '+34 611 222 333',
                'offer' => JobOfferFixtures::SENIOR_PHP, 'minutesAgo' => 120, 'notes' => 'Available from November.',
                'cv' => "Backend engineer, 7 years with PHP.\nSymfony 5–7, Doctrine, DDD and hexagonal architecture at a fintech.\nRabbitMQ consumers, PostgreSQL tuning, PHPUnit, Docker, GitHub Actions.",
                'screening' => ['summary' => 'Backend engineer with 7 years of PHP at a fintech: Symfony, Doctrine, DDD and hexagonal architecture, RabbitMQ consumers and PostgreSQL tuning.', 'years' => 7, 'skills' => ['PHP', 'Symfony', 'Doctrine', 'DDD', 'Hexagonal architecture', 'RabbitMQ', 'PostgreSQL', 'Docker', 'PHPUnit', 'CI/CD']],
                'pipeline' => [Status::InReview],
            ],
            [
                'name' => 'Marco Rossi', 'email' => 'marco.rossi@example.com', 'phone' => null,
                'offer' => JobOfferFixtures::SENIOR_PHP, 'minutesAgo' => 300, 'notes' => null,
                'cv' => "Full-stack developer, 4 years.\nLaravel and Vue.js for e-commerce sites, MySQL, some Docker.\nLooking to grow into backend architecture.",
                'screening' => ['summary' => 'Full-stack developer with 4 years building e-commerce sites with Laravel, Vue.js and MySQL; wants to grow into backend architecture.', 'years' => 4, 'skills' => ['PHP', 'Docker']],
                'pipeline' => [],
            ],
            [
                'name' => 'Aisha Khan', 'email' => 'aisha.khan@example.com', 'phone' => '+44 7700 900123',
                'offer' => JobOfferFixtures::FRONTEND, 'minutesAgo' => 1_440, 'notes' => 'Portfolio: aisha.dev',
                'cv' => "Frontend engineer, 5 years.\nReact, TypeScript, Next.js, Tailwind, design systems with Storybook.\nAccessibility champion (WCAG 2.2), Jest, Testing Library, Playwright.",
                'screening' => ['summary' => 'Frontend engineer with 5 years of React and TypeScript, design systems with Storybook and a strong accessibility focus (WCAG 2.2).', 'years' => 5, 'skills' => ['React', 'TypeScript', 'Tailwind', 'Accessibility', 'Jest', 'Testing Library', 'Playwright', 'Next.js', 'Storybook']],
                'pipeline' => [Status::InReview, Status::Interviewing],
            ],
            [
                'name' => 'Tomás Ruiz', 'email' => 'tomas.ruiz@example.com', 'phone' => '600 123 456',
                'offer' => JobOfferFixtures::DATA_ENGINEER, 'minutesAgo' => 1_680, 'notes' => null,
                'cv' => "Data engineer, 3 years.\nPython, Airflow, advanced SQL, dbt on Snowflake.\nSome AWS (S3, Glue).",
                'screening' => ['summary' => 'Data engineer with 3 years of Python, Airflow and advanced SQL, modelling with dbt on Snowflake and some AWS (S3, Glue).', 'years' => 3, 'skills' => ['Python', 'Airflow', 'dbt', 'SQL', 'AWS']],
                'pipeline' => [],
            ],
            [
                'name' => 'Emma Johansson', 'email' => 'emma.johansson@example.com', 'phone' => null,
                'offer' => JobOfferFixtures::FRONTEND, 'minutesAgo' => 2_880, 'notes' => 'Open to switching to frontend.',
                'cv' => "Backend developer, 6 years.\nJava, Spring Boot, microservices, Kafka.\nBasic HTML/CSS.",
                'screening' => ['summary' => 'Backend developer with 6 years of Java, Spring Boot and Kafka microservices; only basic HTML and CSS.', 'years' => 6, 'skills' => []],
                'pipeline' => [Status::Rejected],
            ],
            [
                'name' => 'Daniel Okafor', 'email' => 'daniel.okafor@example.com', 'phone' => '+34 622 333 444',
                'offer' => JobOfferFixtures::SENIOR_PHP, 'minutesAgo' => 8_640, 'notes' => null,
                'cv' => "Staff engineer, 10 years with PHP.\nSymfony, Doctrine, API Platform, CQRS and event sourcing, RabbitMQ and Kafka.\nPostgreSQL, Docker, PHPUnit. Led migrations to hexagonal architecture; mentoring and CI/CD.",
                'screening' => ['summary' => 'Staff engineer with 10 years of PHP: Symfony, Doctrine and API Platform, CQRS and event sourcing over RabbitMQ and Kafka; led migrations to hexagonal architecture.', 'years' => 10, 'skills' => ['PHP', 'Symfony', 'Doctrine', 'Hexagonal architecture', 'RabbitMQ', 'PostgreSQL', 'Docker', 'PHPUnit', 'CQRS', 'Event-driven design', 'API Platform', 'CI/CD']],
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
                'screening' => ['summary' => 'Software engineer with 4 years of Python services and PostgreSQL, Kafka streaming and Spark jobs on AWS EMR; no Airflow yet.', 'years' => 4, 'skills' => ['Python', 'SQL', 'AWS', 'Kafka', 'Spark']],
                'pipeline' => [Status::InReview],
            ],
        ];
    }
}
