<?php

declare(strict_types=1);

namespace App\Tests\Recruitment\Infrastructure;

use App\Recruitment\Domain\JobApplication\JobApplication;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationRepository;

final class InMemoryJobApplicationRepository implements JobApplicationRepository
{
    /** @var array<string, JobApplication> */
    private array $applications = [];

    public function save(JobApplication $application): void
    {
        $this->applications[$application->id->value] = $application;
    }

    public function find(JobApplicationId $id): ?JobApplication
    {
        return $this->applications[$id->value] ?? null;
    }

    public function count(): int
    {
        return \count($this->applications);
    }
}
