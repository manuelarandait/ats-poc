<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\FindJobApplicationStats;

use App\Recruitment\Application\FindJobApplicationStats\JobApplicationStats;
use PHPUnit\Framework\TestCase;

final class JobApplicationStatsTest extends TestCase
{
    public function test_every_status_is_present_in_pipeline_order_even_without_applications(): void
    {
        $stats = new JobApplicationStats(['hired' => 1, 'received' => 3], [], 0, null);

        self::assertSame(['received' => 3, 'in_review' => 0, 'interviewing' => 0, 'hired' => 1, 'rejected' => 0], $stats->byStatus);
        self::assertSame(4, $stats->total());
    }

    public function test_unknown_keys_count_zero(): void
    {
        $stats = new JobApplicationStats([], ['offer-1' => 2], 0, null);

        self::assertSame(2, $stats->forJobOffer('offer-1'));
        self::assertSame(0, $stats->forJobOffer('offer-2'));
        self::assertSame(0, $stats->forStatus('interviewing'));
    }
}
