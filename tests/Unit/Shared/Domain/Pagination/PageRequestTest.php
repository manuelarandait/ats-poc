<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Pagination;

use App\Shared\Domain\Pagination\PageRequest;
use PHPUnit\Framework\TestCase;

final class PageRequestTest extends TestCase
{
    public function test_the_offset_skips_the_previous_pages(): void
    {
        self::assertSame(20, PageRequest::of(3, 10)->offset());
    }

    public function test_page_and_page_size_are_kept_within_bounds(): void
    {
        $tooLow = PageRequest::of(0, 0);
        $tooHigh = PageRequest::of(1, 1_000);

        self::assertSame(1, $tooLow->page);
        self::assertSame(1, $tooLow->perPage);
        self::assertSame(PageRequest::MAX_PER_PAGE, $tooHigh->perPage);
    }

    public function test_moving_to_another_page_keeps_the_page_size(): void
    {
        $request = PageRequest::of(9, 10)->onPage(2);

        self::assertSame(2, $request->page);
        self::assertSame(10, $request->perPage);
    }
}
