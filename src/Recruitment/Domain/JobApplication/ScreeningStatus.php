<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

/**
 * Where the asynchronous AI enrichment stands for an application.
 * Independent from the hiring pipeline (JobApplicationStatus).
 */
enum ScreeningStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
