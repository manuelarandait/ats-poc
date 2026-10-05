<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SearchJobApplications;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public function opposite(): self
    {
        return self::Asc === $this ? self::Desc : self::Asc;
    }
}
