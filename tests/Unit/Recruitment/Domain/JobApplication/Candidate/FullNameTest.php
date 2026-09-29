<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\JobApplication\Candidate;

use App\Recruitment\Domain\JobApplication\Candidate\FullName;
use App\Recruitment\Domain\JobApplication\Candidate\InvalidFullName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FullNameTest extends TestCase
{
    public function test_it_trims_and_collapses_inner_whitespace(): void
    {
        self::assertSame('José María Pérez', FullName::fromString("  José   María\tPérez ")->value);
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_a_name_with_invalid_length(string $value): void
    {
        $this->expectException(InvalidFullName::class);

        FullName::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'one character' => ['J'];
        yield 'too long' => [str_repeat('a', 151)];
    }
}
