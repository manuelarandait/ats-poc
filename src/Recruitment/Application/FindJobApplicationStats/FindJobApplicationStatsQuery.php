<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FindJobApplicationStats;

use App\Shared\Domain\Bus\Query\Query;

/**
 * Figures for the applications overview (status tabs, KPIs, per-offer counts).
 * Same raw filters as the search except the status: the tabs *are* the status.
 *
 * @implements Query<JobApplicationStats>
 */
final readonly class FindJobApplicationStatsQuery implements Query
{
    public function __construct(
        public ?string $jobOfferId = null,
        public ?string $search = null,
    ) {
    }
}
