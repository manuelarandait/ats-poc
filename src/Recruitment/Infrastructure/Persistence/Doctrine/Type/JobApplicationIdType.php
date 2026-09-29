<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine\Type;

use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * @extends UuidType<JobApplicationId>
 */
final class JobApplicationIdType extends UuidType
{
    protected function uuidClass(): string
    {
        return JobApplicationId::class;
    }
}
