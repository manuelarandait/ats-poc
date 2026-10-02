<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FindJobApplicationStats;

use App\Recruitment\Domain\JobApplication\JobApplicationStatus;

final readonly class JobApplicationStats
{
    /** @var array<string, int> status => count, every status present */
    public array $byStatus;

    /**
     * @param array<string, int> $byStatus     status => count (missing statuses count 0)
     * @param array<string, int> $byJobOffer   job offer id => count
     * @param int                $analysing    applications whose AI screening is pending
     * @param ?int               $averageScore rounded, over screened applications only
     */
    public function __construct(
        array $byStatus,
        public array $byJobOffer,
        public int $analysing,
        public ?int $averageScore,
    ) {
        $all = [];
        foreach (JobApplicationStatus::cases() as $status) {
            $all[$status->value] = $byStatus[$status->value] ?? 0;
        }
        $this->byStatus = $all;
    }

    public function total(): int
    {
        return array_sum($this->byStatus);
    }

    public function forStatus(string $status): int
    {
        return $this->byStatus[$status] ?? 0;
    }

    public function forJobOffer(string $jobOfferId): int
    {
        return $this->byJobOffer[$jobOfferId] ?? 0;
    }
}
