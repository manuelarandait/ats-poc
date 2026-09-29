<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\JobApplication;

use App\Recruitment\Domain\JobApplication\AiScore;
use App\Recruitment\Domain\JobApplication\AiScreening;
use App\Recruitment\Domain\JobApplication\InvalidAiScreening;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AiScreeningTest extends TestCase
{
    #[DataProvider('boundaryScores')]
    public function test_it_accepts_scores_within_range(int $score): void
    {
        self::assertSame($score, AiScore::fromInt($score)->value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function boundaryScores(): iterable
    {
        yield 'minimum' => [0];
        yield 'middle' => [50];
        yield 'maximum' => [100];
    }

    #[DataProvider('outOfRangeScores')]
    public function test_it_rejects_scores_out_of_range(int $score): void
    {
        $this->expectException(InvalidAiScreening::class);

        AiScore::fromInt($score);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfRangeScores(): iterable
    {
        yield 'negative' => [-1];
        yield 'above maximum' => [101];
    }

    public function test_it_trims_the_summary(): void
    {
        $screening = AiScreening::create('  Strong Symfony profile. ', AiScore::fromInt(90));

        self::assertSame('Strong Symfony profile.', $screening->summary);
        self::assertSame(90, $screening->score->value);
    }

    public function test_it_rejects_an_empty_summary(): void
    {
        $this->expectException(InvalidAiScreening::class);

        AiScreening::create('   ', AiScore::fromInt(90));
    }

    public function test_it_rejects_a_summary_above_the_maximum_length(): void
    {
        $this->expectException(InvalidAiScreening::class);

        AiScreening::create(str_repeat('a', AiScreening::SUMMARY_MAX_LENGTH + 1), AiScore::fromInt(90));
    }
}
