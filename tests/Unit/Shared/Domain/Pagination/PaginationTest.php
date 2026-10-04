<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Pagination;

use App\Shared\Domain\Pagination\Pagination;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function test_it_knows_the_pages_and_the_range_shown(): void
    {
        $pagination = new Pagination(total: 25, page: 3, perPage: 10);

        self::assertSame(3, $pagination->pages());
        self::assertSame(21, $pagination->firstItem());
        self::assertSame(25, $pagination->lastItem());
        self::assertTrue($pagination->hasPreviousPage());
        self::assertFalse($pagination->hasNextPage());
    }

    public function test_an_empty_result_still_has_one_page(): void
    {
        $pagination = new Pagination(total: 0, page: 1, perPage: 20);

        self::assertSame(1, $pagination->pages());
        self::assertSame(0, $pagination->firstItem());
        self::assertFalse($pagination->hasNextPage());
        self::assertFalse($pagination->isPastTheEnd());
    }

    public function test_a_page_beyond_the_last_one_is_past_the_end(): void
    {
        self::assertTrue(new Pagination(total: 12, page: 9, perPage: 10)->isPastTheEnd());
        self::assertFalse(new Pagination(total: 12, page: 2, perPage: 10)->isPastTheEnd());
    }
}
