<?php

declare(strict_types=1);

namespace App\Tests\Unit\Screening\Domain;

use App\Screening\Domain\CvAnalysis;
use App\Screening\Domain\InvalidCvAnalysis;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CvAnalysisTest extends TestCase
{
    public function test_it_keeps_a_trimmed_summary_and_the_score(): void
    {
        $analysis = CvAnalysis::create('  Strong PHP profile. ', 87);

        self::assertSame('Strong PHP profile.', $analysis->summary);
        self::assertSame(87, $analysis->score);
    }

    public function test_it_rejects_an_empty_summary(): void
    {
        $this->expectException(InvalidCvAnalysis::class);

        CvAnalysis::create('  ', 50);
    }

    #[DataProvider('outOfRangeScores')]
    public function test_it_rejects_a_score_out_of_range(int $score): void
    {
        $this->expectException(InvalidCvAnalysis::class);

        CvAnalysis::create('Summary', $score);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfRangeScores(): iterable
    {
        yield 'negative' => [-1];
        yield 'above 100' => [101];
    }
}
