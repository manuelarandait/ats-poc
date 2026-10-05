<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SearchJobApplications;

/**
 * Columns the list can be sorted by: a closed set, so a raw query-string
 * value never reaches the ORDER BY.
 */
enum JobApplicationSort: string
{
    case Candidate = 'candidate';
    case Position = 'position';
    case Status = 'status';
    case Score = 'score';
    case AppliedAt = 'applied';

    public static function default(): self
    {
        return self::AppliedAt;
    }

    /**
     * Direction of the first click: A→Z for text and the pipeline, most
     * useful first (best score, newest) for numbers and dates.
     */
    public function defaultDirection(): SortDirection
    {
        return match ($this) {
            self::Candidate, self::Position, self::Status => SortDirection::Asc,
            self::Score, self::AppliedAt => SortDirection::Desc,
        };
    }
}
