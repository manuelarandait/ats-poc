<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\JobApplication\Candidate;

use App\Recruitment\Domain\JobApplication\Candidate\InvalidPhone;
use App\Recruitment\Domain\JobApplication\Candidate\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneTest extends TestCase
{
    #[DataProvider('validPhones')]
    public function test_it_normalizes_a_valid_phone(string $input, string $expected): void
    {
        self::assertSame($expected, Phone::fromString($input)->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validPhones(): iterable
    {
        yield 'international with spaces' => ['+34 600 123 456', '+34600123456'];
        yield 'with dashes and parentheses' => ['(91) 123-45-67', '911234567'];
        yield 'plain digits' => ['600123456', '600123456'];
    }

    #[DataProvider('invalidPhones')]
    public function test_it_rejects_an_invalid_phone(string $value): void
    {
        $this->expectException(InvalidPhone::class);

        Phone::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPhones(): iterable
    {
        yield 'empty' => [''];
        yield 'letters' => ['600-ABC-456'];
        yield 'too few digits' => ['12345'];
        yield 'too many digits' => ['+1234567890123456'];
        yield 'plus in the middle' => ['600+123456'];
    }
}
