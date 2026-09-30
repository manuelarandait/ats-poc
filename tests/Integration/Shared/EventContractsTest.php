<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use App\Recruitment\Application\CompleteJobApplicationScreening\CvScreened as RecruitmentCvScreened;
use App\Recruitment\Application\FailJobApplicationScreening\CvScreeningFailed as RecruitmentCvScreeningFailed;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationSubmitted as RecruitmentJobApplicationSubmitted;
use App\Screening\Application\ScreenCv\JobApplicationSubmitted as ScreeningJobApplicationSubmitted;
use App\Screening\Domain\Event\CvScreened as ScreeningCvScreened;
use App\Screening\Domain\Event\CvScreeningFailed as ScreeningCvScreeningFailed;
use App\Shared\Domain\DomainEvent;
use App\Shared\Infrastructure\Bus\Messenger\JsonEventSerializer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;

/**
 * Consumer-driven contract tests: what a publisher sends must be readable by
 * the class the consumer owns. The only place both sides meet is here, in the
 * tests, never in production code.
 */
final class EventContractsTest extends KernelTestCase
{
    private const string APPLICATION_ID = '0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b';

    public function test_screening_understands_recruitment_job_application_submitted(): void
    {
        $at = new \DateTimeImmutable('2026-09-30 10:00:00.123456');

        $received = $this->sendThroughTheWire(new RecruitmentJobApplicationSubmitted(self::APPLICATION_ID, 'offer-id', 'Senior PHP', 'Symfony, DDD', "Jane Doe\nPHP, 6 years", $at));

        self::assertEquals(new ScreeningJobApplicationSubmitted(self::APPLICATION_ID, 'Senior PHP', 'Symfony, DDD', "Jane Doe\nPHP, 6 years", $at), $received);
    }

    public function test_recruitment_understands_screening_cv_screened(): void
    {
        $at = new \DateTimeImmutable('2026-09-30 10:00:01');

        $received = $this->sendThroughTheWire(new ScreeningCvScreened(self::APPLICATION_ID, 'Great fit.', 91, $at));

        self::assertEquals(new RecruitmentCvScreened(self::APPLICATION_ID, 'Great fit.', 91, $at), $received);
    }

    public function test_recruitment_understands_screening_cv_screening_failed(): void
    {
        $at = new \DateTimeImmutable('2026-09-30 10:00:02');

        $received = $this->sendThroughTheWire(new ScreeningCvScreeningFailed(self::APPLICATION_ID, 'LLM down', $at));

        self::assertEquals(new RecruitmentCvScreeningFailed(self::APPLICATION_ID, 'LLM down', $at), $received);
    }

    private function sendThroughTheWire(DomainEvent $published): object
    {
        $serializer = self::getContainer()->get(JsonEventSerializer::class);

        return $serializer->decode($serializer->encode(new Envelope($published)))->getMessage();
    }
}
