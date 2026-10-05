<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Persistence\Doctrine;

use App\Recruitment\Domain\JobApplication\JobApplication;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationRepository;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\ScreeningStatus;
use App\Recruitment\Domain\JobApplication\SkillMatch;
use App\Tests\Recruitment\Domain\JobApplication\CandidateMother;
use App\Tests\Recruitment\Domain\JobApplication\JobApplicationMother;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineJobApplicationRepositoryTest extends KernelTestCase
{
    private JobApplicationRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $this->repository = $container->get(JobApplicationRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    public function test_it_persists_and_reloads_a_submitted_application(): void
    {
        $application = JobApplicationMother::submitted(
            candidate: CandidateMother::create(fullName: 'Jane Doe', email: 'jane@example.com', phone: '+34 600 123 456'),
            cv: "Line one\n  indented line two",
            notes: 'Available in October',
            appliedAt: new \DateTimeImmutable('2026-09-01 10:00:00'),
        );

        $reloaded = $this->saveAndReload($application);

        self::assertTrue($application->id->equals($reloaded->id));
        self::assertTrue($application->jobOfferId->equals($reloaded->jobOfferId));
        self::assertSame('Jane Doe', $reloaded->candidate->fullName->value);
        self::assertSame('jane@example.com', $reloaded->candidate->email->value);
        self::assertSame('+34600123456', $reloaded->candidate->phone?->value);
        self::assertSame("Line one\n  indented line two", $reloaded->cv->value);
        self::assertSame('Available in October', $reloaded->notes?->value);
        self::assertEquals(new \DateTimeImmutable('2026-09-01 10:00:00'), $reloaded->appliedAt);
        self::assertSame(JobApplicationStatus::Received, $reloaded->status);
    }

    public function test_optional_data_and_a_pending_screening_come_back_as_null(): void
    {
        $application = JobApplicationMother::submitted(candidate: CandidateMother::create(phone: null), notes: null);

        $reloaded = $this->saveAndReload($application);

        self::assertNull($reloaded->candidate->phone);
        self::assertNull($reloaded->notes);
        self::assertSame(ScreeningStatus::Pending, $reloaded->screeningStatus);
        self::assertNull($reloaded->aiScreening);
        self::assertNull($reloaded->screenedAt);
    }

    public function test_it_persists_the_screening_result_and_status_changes(): void
    {
        $application = JobApplicationMother::persisted();
        $skills = [SkillMatch::create('Symfony', true, true), SkillMatch::create('Kafka', false, false)];
        $application->completeScreening(JobApplicationMother::aiScreening(score: 73, summary: 'Solid PHP profile.', skills: $skills), new \DateTimeImmutable('2026-09-02 09:00:00'));
        $application->changeStatus(JobApplicationStatus::InReview, new \DateTimeImmutable('2026-09-02 10:00:00'));

        $reloaded = $this->saveAndReload($application);

        self::assertSame(ScreeningStatus::Completed, $reloaded->screeningStatus);
        self::assertSame(73, $reloaded->aiScreening?->score->value);
        self::assertSame('Solid PHP profile.', $reloaded->aiScreening->summary);
        self::assertEquals($skills, $reloaded->aiScreening->skills);
        self::assertEquals(new \DateTimeImmutable('2026-09-02 09:00:00'), $reloaded->screenedAt);
        self::assertSame(JobApplicationStatus::InReview, $reloaded->status);
        self::assertEquals(new \DateTimeImmutable('2026-09-02 10:00:00'), $reloaded->updatedAt);
    }

    public function test_it_returns_null_for_an_unknown_id(): void
    {
        self::assertNull($this->repository->find(JobApplicationId::fromString('0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b')));
    }

    private function saveAndReload(JobApplication $application): JobApplication
    {
        $this->repository->save($application);
        $this->entityManager->clear(); // force a real read from the database

        $reloaded = $this->repository->find($application->id);
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
