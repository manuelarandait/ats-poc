<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Application;

use App\Recruitment\Application\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Domain\JobApplication\Candidate\InvalidEmail;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationSubmitted;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationRepository;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobOffer\JobOfferNotFound;
use App\Recruitment\Domain\JobOffer\JobOfferRepository;
use App\Shared\Domain\Bus\Command\CommandBus;
use App\Tests\Recruitment\Domain\JobOffer\JobOfferMother;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The whole write path with real wiring: command bus → handler → Doctrine
 * (inside a transaction) → domain event queued on the async transport.
 */
final class SubmitJobApplicationTest extends KernelTestCase
{
    private const string ID = '0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b';

    private CommandBus $commandBus;
    private InMemoryTransport $asyncTransport;
    private string $jobOfferId;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $this->commandBus = $container->get(CommandBus::class);
        $transport = $container->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $this->asyncTransport = $transport;

        $offer = JobOfferMother::create();
        $container->get(JobOfferRepository::class)->save($offer);
        $this->jobOfferId = $offer->id->value;
    }

    public function test_submitting_persists_the_application_and_queues_the_event_for_async_processing(): void
    {
        $this->commandBus->dispatch($this->command());

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(JobApplicationRepository::class)->find(JobApplicationId::fromString(self::ID));
        self::assertNotNull($stored);
        self::assertSame(JobApplicationStatus::Received, $stored->status);

        $sent = $this->asyncTransport->getSent();
        self::assertCount(1, $sent);
        $event = $sent[0]->getMessage();
        self::assertInstanceOf(JobApplicationSubmitted::class, $event);
        self::assertSame(self::ID, $event->aggregateId);
        self::assertSame($this->jobOfferId, $event->jobOfferId);
        self::assertSame('event.bus', $sent[0]->last(BusNameStamp::class)?->getBusName(), 'Events must travel on the event bus.');
    }

    public function test_domain_errors_reach_the_caller_unwrapped_and_nothing_is_queued(): void
    {
        try {
            $this->commandBus->dispatch($this->command(email: 'not-an-email'));
            self::fail('Expected an invalid email.');
        } catch (InvalidEmail) {
            self::assertSame([], $this->asyncTransport->getSent());
        }
    }

    public function test_applying_to_an_unknown_offer_fails_without_side_effects(): void
    {
        try {
            $this->commandBus->dispatch($this->command(jobOfferId: '0192f5a0-0000-7000-8000-00000000dead'));
            self::fail('Expected an unknown job offer.');
        } catch (JobOfferNotFound) {
            self::assertNull(self::getContainer()->get(JobApplicationRepository::class)->find(JobApplicationId::fromString(self::ID)));
            self::assertSame([], $this->asyncTransport->getSent());
        }
    }

    private function command(?string $jobOfferId = null, string $email = 'jane@example.com'): SubmitJobApplicationCommand
    {
        return new SubmitJobApplicationCommand(
            id: self::ID,
            jobOfferId: $jobOfferId ?? $this->jobOfferId,
            fullName: 'Jane Doe',
            email: $email,
            phone: null,
            cv: 'Backend engineer, 6 years with PHP and Symfony.',
            notes: null,
        );
    }
}
