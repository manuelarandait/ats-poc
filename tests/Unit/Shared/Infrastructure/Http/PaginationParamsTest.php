<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\PaginationParams;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PaginationParamsTest extends TestCase
{
    public function test_it_reads_the_page_and_an_offered_page_size(): void
    {
        $params = PaginationParams::fromRequest(Request::create('/list?page=3&perPage=50'), [10, 20, 50]);

        self::assertSame(3, $params->page);
        self::assertSame(50, $params->perPage);
        self::assertSame(['perPage' => 50], $params->linkParams());
    }

    public function test_a_page_size_not_offered_falls_back_to_the_default(): void
    {
        $params = PaginationParams::fromRequest(Request::create('/list?perPage=7'), [10, 20, 50]);

        self::assertSame(1, $params->page);
        self::assertSame(20, $params->perPage);
    }

    public function test_the_default_page_size_stays_out_of_the_links(): void
    {
        $params = PaginationParams::fromRequest(Request::create('/list?perPage=20'), [10, 20, 50]);

        self::assertSame([], $params->linkParams());
    }
}
