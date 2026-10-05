<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Infrastructure\Twig;

use App\Recruitment\Infrastructure\Twig\ScoreBandExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScoreBandExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function scores(): iterable
    {
        yield 'perfect fit' => [100, 'high'];
        yield 'lowest high' => [70, 'high'];
        yield 'highest medium' => [69, 'medium'];
        yield 'lowest medium' => [40, 'medium'];
        yield 'highest low' => [39, 'low'];
        yield 'no fit' => [0, 'low'];
    }

    #[DataProvider('scores')]
    public function test_band(int $score, string $band): void
    {
        self::assertSame($band, new ScoreBandExtension()->band($score));
    }
}
