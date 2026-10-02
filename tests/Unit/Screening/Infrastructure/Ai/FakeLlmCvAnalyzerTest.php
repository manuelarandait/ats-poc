<?php

declare(strict_types=1);

namespace App\Tests\Unit\Screening\Infrastructure\Ai;

use App\Screening\Domain\CvAnalysisUnavailable;
use App\Screening\Domain\Position;
use App\Screening\Infrastructure\Ai\FakeLlmCvAnalyzer;
use PHPUnit\Framework\TestCase;

final class FakeLlmCvAnalyzerTest extends TestCase
{
    private FakeLlmCvAnalyzer $analyzer;
    private Position $backendPosition;

    protected function setUp(): void
    {
        $this->analyzer = new FakeLlmCvAnalyzer(latencyMs: 0, failureRate: 0.0);
        $this->backendPosition = new Position(
            'Senior PHP Backend Engineer',
            'PHP, Symfony, Doctrine, DDD, RabbitMQ, PostgreSQL, Docker and PHPUnit.',
        );
    }

    public function test_a_cv_matching_the_position_scores_higher_than_an_unrelated_one(): void
    {
        $matching = $this->analyzer->analyse('Backend engineer, 8 years with PHP, Symfony, Doctrine, DDD, RabbitMQ, PostgreSQL, Docker, PHPUnit.', $this->backendPosition);
        $unrelated = $this->analyzer->analyse('Frontend developer, 2 years with React and TypeScript.', $this->backendPosition);

        self::assertGreaterThan($unrelated->score, $matching->score);
    }

    public function test_full_skill_coverage_and_senior_experience_score_the_maximum(): void
    {
        $analysis = $this->analyzer->analyse('Engineer, 10 years. PHP Symfony Doctrine DDD RabbitMQ PostgreSQL Docker PHPUnit.', $this->backendPosition);

        self::assertSame(100, $analysis->score);
    }

    public function test_no_matching_skill_and_no_experience_scores_zero(): void
    {
        $analysis = $this->analyzer->analyse('Chef specialised in Italian cuisine.', $this->backendPosition);

        self::assertSame(0, $analysis->score);
    }

    public function test_the_same_input_always_produces_the_same_analysis(): void
    {
        $cv = 'Backend engineer, 5 years with PHP and Symfony.';

        self::assertEquals($this->analyzer->analyse($cv, $this->backendPosition), $this->analyzer->analyse($cv, $this->backendPosition));
    }

    public function test_the_summary_describes_the_profile_then_the_fit_for_the_position(): void
    {
        $analysis = $this->analyzer->analyse("Backend engineer at Acme\n5 years with PHP and Symfony.", $this->backendPosition);

        self::assertSame(
            'Backend engineer with 5 years of experience. Main skills: PHP, Symfony.'
            .' Matches 2 of 8 key skills for Senior PHP Backend Engineer: PHP, Symfony.'
            .' Missing: Doctrine, DDD, RabbitMQ, PostgreSQL, Docker, PHPUnit.',
            $analysis->summary,
        );
    }

    public function test_the_role_skips_the_name_line_and_drops_the_details_around_the_title(): void
    {
        $cv = "Jane Doe\nSenior Full-Stack Engineer (Symfony / Vue) - Freelance\nMadrid\n9 years building web products.";

        $analysis = $this->analyzer->analyse($cv, $this->backendPosition);

        self::assertStringStartsWith('Senior Full-Stack Engineer with 9 years of experience. Main skills: Symfony, Vue.js.', $analysis->summary);
    }

    public function test_a_cv_written_in_spanish_is_understood_too(): void
    {
        $cv = "Ana López\ndesarrolladora backend en Acme\nCasi 11 años con PHP, Symfony y Docker.";

        $analysis = $this->analyzer->analyse($cv, $this->backendPosition);

        self::assertStringStartsWith('Desarrolladora backend with 11 years of experience. Main skills: PHP, Symfony, Docker.', $analysis->summary);
    }

    public function test_main_skills_follow_the_order_of_the_cv_and_are_capped(): void
    {
        $cv = 'Kubernetes, Redis, Python, PHP, React, TypeScript and Docker.';

        $analysis = $this->analyzer->analyse($cv, $this->backendPosition);

        self::assertStringStartsWith('Main skills: Kubernetes, Redis, Python, PHP, React (+2 more).', $analysis->summary);
    }

    public function test_a_cv_without_role_experience_or_skills_says_so_instead_of_inventing_a_profile(): void
    {
        $analysis = $this->analyzer->analyse('test', $this->backendPosition);

        self::assertStringStartsWith("The CV gives too little detail to describe the candidate's profile. Matches 0 of 8", $analysis->summary);
    }

    public function test_a_cv_with_the_failure_marker_makes_the_llm_unavailable(): void
    {
        $this->expectException(CvAnalysisUnavailable::class);

        $this->analyzer->analyse('PHP developer '.FakeLlmCvAnalyzer::FAILURE_MARKER, $this->backendPosition);
    }

    public function test_a_failure_rate_of_one_always_fails(): void
    {
        $this->expectException(CvAnalysisUnavailable::class);

        new FakeLlmCvAnalyzer(latencyMs: 0, failureRate: 1.0)->analyse('PHP developer', $this->backendPosition);
    }

    public function test_a_missing_nice_to_have_skill_costs_half_as_much_as_a_missing_required_one(): void
    {
        // Weights: PHP 1 + Symfony 1 (required) + Kafka 0.5 + Docker 0.5 (nice to have) = 3
        $position = new Position('Backend Engineer', "Requirements: PHP, Symfony.\nNice to have: Kafka, Docker.");

        $missesDocker = $this->analyzer->analyse('PHP, Symfony and Kafka developer.', $position);   // 2.5 / 3
        $missesSymfony = $this->analyzer->analyse('PHP, Kafka and Docker developer.', $position);   // 2 / 3

        self::assertSame(67, $missesDocker->score);
        self::assertSame(53, $missesSymfony->score);
    }

    public function test_the_summary_separates_missing_required_from_missing_nice_to_have(): void
    {
        $position = new Position('Backend Engineer', "Requirements: PHP, Symfony.\nNice to have: Kafka, Docker.");

        $analysis = $this->analyzer->analyse('PHP developer with Docker.', $position);

        self::assertStringContainsString('Matches 2 of 4 key skills for Backend Engineer: PHP, Docker.', $analysis->summary);
        self::assertStringContainsString('Missing: Symfony.', $analysis->summary);
        self::assertStringContainsString('Nice to have, missing: Kafka.', $analysis->summary);
    }
}
