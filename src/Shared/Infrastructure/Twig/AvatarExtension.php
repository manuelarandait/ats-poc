<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Initials avatars: "Jane van Doe" → "JD", and a tone index that is stable
 * for a given name, so the same person always gets the same colour.
 */
final class AvatarExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('initials', $this->initials(...)),
            new TwigFilter('avatar_tone', $this->tone(...)),
        ];
    }

    public function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        if ([] === $words) {
            return '?';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = \count($words) > 1 ? mb_substr($words[array_key_last($words)], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /**
     * @param positive-int $tones
     */
    public function tone(string $name, int $tones): int
    {
        return crc32(mb_strtolower(trim($name))) % $tones;
    }
}
