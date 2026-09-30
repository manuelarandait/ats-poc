<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

/**
 * Port: what the domain needs from persistence. Implemented in infrastructure.
 */
interface JobApplicationRepository
{
    public function save(JobApplication $application): void;

    public function find(JobApplicationId $id): ?JobApplication;
}
