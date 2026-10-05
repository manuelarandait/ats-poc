<?php

declare(strict_types=1);

namespace App\Tests\Unit\Screening\Application\ScreenCv;

use App\Screening\Application\ScreenCv\JobApplicationSubmitted;
use App\Screening\Application\ScreenCv\ScreenCvOnJobApplicationSubmitted;
use App\Screening\Domain\CvAnalysis;
use App\Screening\Domain\CvAnalysisUnavailable;
use App\Screening\Domain\CvAnalyzer;
use App\Screening\Domain\Event\CvScreened;
use App\Screening\Domain\Position;
use App\Screening\Domain\SkillMatch;
use App\Tests\Shared\Infrastructure\SpyEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ScreenCvOnJobApplicationSubmittedTest extends TestCase
{
    private const string APPLICATION_ID = '0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b';
    private const string NOW = '2026-09-30 10:00:00';

    private SpyEventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new SpyEventBus();
    }

    public function test_it_analyses_the_cv_against_the_position_and_publishes_the_result(): void
    {
        $analyzer = new class implements CvAnalyzer {
            public ?Position $position = null;
            public ?string $cv = null;

            public function analyse(string $cv, Position $position): CvAnalysis
            {
                $this->cv = $cv;
                $this->position = $position;

                return CvAnalysis::create('Great fit.', 90, [new SkillMatch('Symfony', true, true), new SkillMatch('Kafka', false, false)]);
            }
        };

        new ScreenCvOnJobApplicationSubmitted($analyzer, $this->eventBus, new MockClock(self::NOW))($this->event());

        self::assertSame('PHP developer, 6 years.', $analyzer->cv);
        self::assertEquals(new Position('Senior PHP Developer', 'Symfony and DDD.'), $analyzer->position);
        self::assertEquals(
            [new CvScreened(self::APPLICATION_ID, 'Great fit.', 90, [
                ['skill' => 'Symfony', 'required' => true, 'matched' => true],
                ['skill' => 'Kafka', 'required' => false, 'matched' => false],
            ], new \DateTimeImmutable(self::NOW))],
            $this->eventBus->published,
        );
    }

    public function test_an_unavailable_ai_bubbles_up_so_the_message_is_retried_and_nothing_is_published(): void
    {
        $analyzer = new class implements CvAnalyzer {
            public function analyse(string $cv, Position $position): CvAnalysis
            {
                throw CvAnalysisUnavailable::because('timeout');
            }
        };

        try {
            new ScreenCvOnJobApplicationSubmitted($analyzer, $this->eventBus, new MockClock(self::NOW))($this->event());
            self::fail('Expected the failure to bubble up.');
        } catch (CvAnalysisUnavailable) {
            self::assertSame([], $this->eventBus->published);
        }
    }

    private function event(): JobApplicationSubmitted
    {
        return new JobApplicationSubmitted(self::APPLICATION_ID, 'Senior PHP Developer', 'Symfony and DDD.', 'PHP developer, 6 years.', new \DateTimeImmutable('2026-09-30 09:59:00'));
    }
}
