<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Fixtures;

use App\Recruitment\Domain\JobOffer\JobOffer;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * The fixed job-offer catalog. Deliberately different profiles, so the AI
 * relevance score varies depending on which offer a CV is sent to.
 */
final class JobOfferFixtures extends Fixture
{
    // Stable ids: links and bookmarks survive a fixtures reload.
    public const string SENIOR_PHP = '0192f5a0-0000-7000-8000-000000000001';
    public const string FRONTEND = '0192f5a0-0000-7000-8000-000000000002';
    public const string DATA_ENGINEER = '0192f5a0-0000-7000-8000-000000000003';

    public function load(ObjectManager $manager): void
    {
        $offers = [
            self::SENIOR_PHP => [
                'Senior PHP Backend Engineer',
                <<<'TXT'
                    Join the team building our recruiting platform backend.
                    You will design and evolve services with PHP 8 and Symfony following DDD and hexagonal
                    architecture, integrate asynchronous processes through RabbitMQ and own their PostgreSQL
                    persistence. Requirements: 5+ years with PHP, strong Symfony and Doctrine knowledge,
                    automated testing (PHPUnit), Docker, messaging/queues and clean code practices.
                    Nice to have: CQRS, event-driven design, API Platform, CI/CD.
                    TXT,
            ],
            self::FRONTEND => [
                'Frontend Engineer (React / TypeScript)',
                <<<'TXT'
                    Build fast, accessible interfaces for recruiters and candidates.
                    You will develop features with React and TypeScript, contribute to our design system and
                    care about accessibility (WCAG), performance and UX details. Requirements: 3+ years with
                    React and TypeScript, CSS/Tailwind, component testing (Jest, Testing Library) and end-to-end
                    testing (Playwright). Nice to have: Next.js, Storybook, design sensibility.
                    TXT,
            ],
            self::DATA_ENGINEER => [
                'Data Engineer (Python)',
                <<<'TXT'
                    Own the pipelines that turn recruiting activity into insights.
                    You will build and operate batch and streaming data pipelines with Python, Airflow and dbt,
                    model data in the warehouse with advanced SQL and run workloads on AWS. Requirements: 3+ years
                    with Python and SQL, orchestration (Airflow), data modelling, cloud (AWS).
                    Nice to have: Spark, Kafka, data quality tooling.
                    TXT,
            ],
        ];

        foreach ($offers as $id => [$title, $description]) {
            $manager->persist(JobOffer::create(JobOfferId::fromString($id), $title, $description));
        }

        $manager->flush();
    }
}
