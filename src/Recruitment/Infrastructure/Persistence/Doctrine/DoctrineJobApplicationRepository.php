<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine;

use App\Recruitment\Domain\JobApplication\JobApplication;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineJobApplicationRepository implements JobApplicationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(JobApplication $application): void
    {
        $this->entityManager->persist($application);
        $this->entityManager->flush();
    }

    public function find(JobApplicationId $id): ?JobApplication
    {
        return $this->entityManager->find(JobApplication::class, $id);
    }
}
