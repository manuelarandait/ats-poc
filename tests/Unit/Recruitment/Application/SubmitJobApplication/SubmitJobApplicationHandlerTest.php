<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\SubmitJobApplication;

use App\Recruitment\Application\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Application\SubmitJobApplication\SubmitJobApplicationHandler;
use App\Recruitment\Domain\JobApplication\Candidate\InvalidEmail;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationSubmitted;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\ScreeningStatus;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Recruitment\Domain\JobOffer\JobOfferNotFound;
use App\Tests\Recruitment\Domain\JobOffer\JobOfferMother;
use App\Tests\Recruitment\Infrastructure\InMemoryJobApplicationRepository;
use App\Tests\Recruitment\Infrastructure\InMemoryJobOfferRepository;
use App\Tests\Shared\Infrastructure\SpyEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class SubmitJobApplicationHandlerTest extends TestCase
{
    private const string ID = '0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b';
    private const string OFFER_ID = '0192f5a0-0000-7000-8000-000000000001';
    private const string NOW = '2026-09-29 10:00:00';

    private InMemoryJobApplicationRepository $applications;
    private SpyEventBus $eventBus;
    private SubmitJobApplicationHandler $handler;

    protected function setUp(): void
    {
        $offers = new InMemoryJobOfferRepository();
        $offers->save(JobOfferMother::create(id: JobOfferId::fromString(self::OFFER_ID), title: 'Senior PHP Developer', description: 'Symfony and DDD.'));

        $this->applications = new InMemoryJobApplicationRepository();
        $this->eventBus = new SpyEventBus();
        $this->handler = new SubmitJobApplicationHandler($this->applications, $offers, $this->eventBus, new MockClock(self::NOW));
    }

    public function test_it_stores_a_received_application_applied_now(): void
    {
        ($this->handler)($this->command());

        $application = $this->applications->find(JobApplicationId::fromString(self::ID));
        self::assertNotNull($application);
        self::assertSame(JobApplicationStatus::Received, $application->status);
        self::assertSame(ScreeningStatus::Pending, $application->screeningStatus);
        self::assertEquals(new \DateTimeImmutable(self::NOW), $application->appliedAt);
        self::assertSame('jane@example.com', $application->candidate->email->value);
        self::assertNull($application->candidate->phone);
        self::assertNull($application->notes);
    }

    public function test_it_publishes_job_application_submitted_with_cv_and_position(): void
    {
        ($this->handler)($this->command());

        self::assertEquals(
            [new JobApplicationSubmitted(self::ID, self::OFFER_ID, 'Senior PHP Developer', 'Symfony and DDD.', 'Backend engineer, 6 years with PHP and Symfony.', new \DateTimeImmutable(self::NOW))],
            $this->eventBus->published,
        );
    }

    public function test_it_rejects_an_application_to_an_unknown_job_offer(): void
    {
        $this->expectException(JobOfferNotFound::class);

        ($this->handler)($this->command(jobOfferId: '0192f5a0-0000-7000-8000-00000000dead'));
    }

    public function test_invalid_candidate_data_stores_and_publishes_nothing(): void
    {
        try {
            ($this->handler)($this->command(email: 'not-an-email'));
            self::fail('Expected an invalid email.');
        } catch (InvalidEmail) {
            self::assertSame(0, $this->applications->count());
            self::assertSame([], $this->eventBus->published);
        }
    }

    private function command(string $jobOfferId = self::OFFER_ID, string $email = 'Jane@Example.com'): SubmitJobApplicationCommand
    {
        return new SubmitJobApplicationCommand(
            id: self::ID,
            jobOfferId: $jobOfferId,
            fullName: 'Jane Doe',
            email: $email,
            phone: '  ',
            cv: 'Backend engineer, 6 years with PHP and Symfony.',
            notes: '',
        );
    }
}
