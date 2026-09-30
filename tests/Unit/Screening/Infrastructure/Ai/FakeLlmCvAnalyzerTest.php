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

    public function test_the_summary_explains_the_headline_experience_matched_and_missing_skills(): void
    {
        $analysis = $this->analyzer->analyse("Backend engineer at Acme\n5 years with PHP and Symfony.", $this->backendPosition);

        self::assertStringStartsWith('Backend engineer at Acme.', $analysis->summary);
        self::assertStringContainsString('5 years of experience', $analysis->summary);
        self::assertStringContainsString('Matches 2 of 8 key skills for Senior PHP Backend Engineer: PHP, Symfony.', $analysis->summary);
        self::assertStringContainsString('Missing: Doctrine, DDD, RabbitMQ, PostgreSQL, Docker, PHPUnit.', $analysis->summary);
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
}
