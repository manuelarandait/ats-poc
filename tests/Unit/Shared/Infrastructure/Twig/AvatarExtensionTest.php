<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Twig;

use App\Shared\Infrastructure\Twig\AvatarExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AvatarExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'first and last name' => ['Jane Doe', 'JD'];
        yield 'middle names are skipped' => ['Jane van der Doe', 'JD'];
        yield 'single name' => ['Cher', 'C'];
        yield 'lowercase and extra spaces' => ['  ana   lópez ', 'AL'];
        yield 'multibyte initials' => ['Élodie Ñúñez', 'ÉÑ'];
        yield 'blank' => ['   ', '?'];
    }

    #[DataProvider('names')]
    public function test_initials(string $name, string $initials): void
    {
        self::assertSame($initials, new AvatarExtension()->initials($name));
    }

    public function test_the_tone_is_stable_for_a_name_and_within_range(): void
    {
        $extension = new AvatarExtension();

        self::assertSame($extension->tone('Jane Doe', 5), $extension->tone(' jane doe', 5));
        foreach (['Jane Doe', 'John Smith', 'Ana López', 'Wei Zhang', 'Omar Haddad'] as $name) {
            self::assertContains($extension->tone($name, 5), range(0, 4));
        }
    }
}
