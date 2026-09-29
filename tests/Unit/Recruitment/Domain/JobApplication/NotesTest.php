<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\JobApplication;

use App\Recruitment\Domain\JobApplication\InvalidNotes;
use App\Recruitment\Domain\JobApplication\Notes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotesTest extends TestCase
{
    #[DataProvider('blankInputs')]
    public function test_blank_input_means_no_notes(?string $value): void
    {
        self::assertNull(Notes::fromNullable($value));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function blankInputs(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'whitespace' => ["  \n "];
    }

    public function test_it_trims_the_notes(): void
    {
        self::assertSame('Available from October', Notes::fromNullable('  Available from October ')?->value);
    }

    public function test_it_rejects_notes_above_the_maximum_length(): void
    {
        $this->expectException(InvalidNotes::class);

        Notes::fromNullable(str_repeat('a', Notes::MAX_LENGTH + 1));
    }
}
