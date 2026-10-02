<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SearchJobApplications;

final readonly class JobApplicationPage
{
    /**
     * @param list<JobApplicationSummary> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public JobApplicationSort $sort = JobApplicationSort::AppliedAt,
        public SortDirection $direction = SortDirection::Desc,
    ) {
    }

    /**
     * What clicking a column header does: flip the direction of the current
     * column, or start another column in its natural direction.
     */
    public function nextDirection(string $column): SortDirection
    {
        $sort = JobApplicationSort::from($column);

        return $sort === $this->sort ? $this->direction->opposite() : $sort->defaultDirection();
    }

    public function isDefaultSort(): bool
    {
        return self::isDefault($this->sort, $this->direction);
    }

    /**
     * Whether sorting by this column in this direction is the default order,
     * so links can leave it out of the URL.
     */
    public function isDefaultSortFor(string $column, string $direction): bool
    {
        return self::isDefault(JobApplicationSort::from($column), SortDirection::from($direction));
    }

    private static function isDefault(JobApplicationSort $sort, SortDirection $direction): bool
    {
        return JobApplicationSort::default() === $sort && $sort->defaultDirection() === $direction;
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->pages();
    }
}
