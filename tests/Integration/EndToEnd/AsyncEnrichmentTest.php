<?php

declare(strict_types=1);

namespace App\Tests\Integration\EndToEnd;

use App\Recruitment\Application\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Domain\JobApplication\JobApplication;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationRepository;
use App\Recruitment\Domain\JobApplication\ScreeningStatus;
use App\Recruitment\Domain\JobApplication\SkillMatch;
use App\Recruitment\Domain\JobOffer\JobOfferRepository;
use App\Screening\Infrastructure\Ai\FakeLlmCvAnalyzer;
use App\Shared\Domain\Bus\Command\CommandBus;
use App\Tests\Recruitment\Domain\JobOffer\JobOfferMother;
use App\Tests\Shared\Infrastructure\Messenger\ConsumesAsyncMessages;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The whole asynchronous enrichment across both contexts, with a real worker:
 *
 *   submit → JobApplicationSubmitted ⇢ Screening (mock LLM) ⇢ CvScreened / CvScreeningFailed ⇢ Recruitment
 */
final class AsyncEnrichmentTest extends KernelTestCase
{
    use ConsumesAsyncMessages;

    private const string ID = '0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b';

    private string $jobOfferId;

    protected function setUp(): void
    {
        $offer = JobOfferMother::create(title: 'Senior PHP Backend Engineer', description: 'PHP, Symfony, DDD and RabbitMQ.');
        self::getContainer()->get(JobOfferRepository::class)->save($offer);
        $this->jobOfferId = $offer->id->value;
    }

    public function test_a_submitted_application_is_pending_until_the_worker_enriches_it(): void
    {
        $this->submit('Backend engineer, 7 years with PHP, Symfony, DDD and RabbitMQ.');

        self::assertSame(ScreeningStatus::Pending, $this->application()->screeningStatus);

        $this->consumeAsyncMessages();

        $application = $this->application();
        self::assertSame(ScreeningStatus::Completed, $application->screeningStatus);
        self::assertNotNull($application->aiScreening);
        self::assertSame('Backend engineer with 7 years of experience. Main skills: PHP, Symfony, DDD, RabbitMQ.', $application->aiScreening->summary);
        self::assertSame(['PHP', 'Symfony', 'DDD', 'RabbitMQ'], array_map(static fn (SkillMatch $skill): string => $skill->skill, array_filter($application->aiScreening->skills, static fn (SkillMatch $skill): bool => $skill->matched)));
        self::assertSame(98, $application->aiScreening->score->value); // 80 × 4/4 + 20 × 7/8
        self::assertNotNull($application->screenedAt);
    }

    public function test_when_the_llm_keeps_failing_the_message_is_retried_then_the_screening_is_marked_as_failed(): void
    {
        $this->submit('PHP developer '.FakeLlmCvAnalyzer::FAILURE_MARKER);

        $this->consumeAsyncMessages();

        $application = $this->application();
        self::assertSame(ScreeningStatus::Failed, $application->screeningStatus);
        self::assertNull($application->aiScreening);

        // The original message is kept in the failure transport for inspection/replay.
        $failed = self::getContainer()->get('messenger.transport.failed');
        self::assertInstanceOf(InMemoryTransport::class, $failed);
        self::assertCount(1, $failed->getSent());
    }

    private function submit(string $cv): void
    {
        self::getContainer()->get(CommandBus::class)->dispatch(new SubmitJobApplicationCommand(
            id: self::ID,
            jobOfferId: $this->jobOfferId,
            fullName: 'Jane Doe',
            email: 'jane@example.com',
            phone: null,
            cv: $cv,
            notes: null,
        ));
    }

    private function application(): JobApplication
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $application = self::getContainer()->get(JobApplicationRepository::class)->find(JobApplicationId::fromString(self::ID));
        self::assertNotNull($application);

        return $application;
    }
}
