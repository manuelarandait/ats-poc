<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\JobApplication\Candidate;

use App\Recruitment\Domain\JobApplication\Candidate\Email;
use App\Recruitment\Domain\JobApplication\Candidate\InvalidEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function test_it_normalizes_to_trimmed_lowercase(): void
    {
        self::assertSame('jane.doe@example.com', Email::fromString('  Jane.Doe@Example.COM ')->value);
    }

    #[DataProvider('invalidEmails')]
    public function test_it_rejects_an_invalid_email(string $value): void
    {
        $this->expectException(InvalidEmail::class);

        Email::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'no at sign' => ['jane.example.com'];
        yield 'no domain' => ['jane@'];
        yield 'spaces inside' => ['jane doe@example.com'];
        yield 'too long' => [str_repeat('a', 250).'@example.com'];
    }

    public function test_emails_are_compared_by_value(): void
    {
        self::assertTrue(Email::fromString('JANE@example.com')->equals(Email::fromString('jane@example.com')));
    }
}
