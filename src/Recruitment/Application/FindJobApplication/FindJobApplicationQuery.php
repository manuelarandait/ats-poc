<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FindJobApplication;

use App\Shared\Domain\Bus\Query\Query;

/**
 * @implements Query<JobApplicationDetails>
 */
final readonly class FindJobApplicationQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
