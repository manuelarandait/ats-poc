<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Twig;

use Psr\Clock\ClockInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * "3 hours ago"-style relative dates for lists and timestamps.
 */
final class TimeAgoExtension extends AbstractExtension
{
    private const array UNITS = [
        'year' => 31_536_000,
        'month' => 2_592_000,
        'day' => 86_400,
        'hour' => 3_600,
        'minute' => 60,
    ];

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function getFilters(): array
    {
        return [new TwigFilter('time_ago', $this->timeAgo(...))];
    }

    public function timeAgo(\DateTimeInterface $date): string
    {
        $seconds = $this->clock->now()->getTimestamp() - $date->getTimestamp();

        if ($seconds < 60) {
            return 'just now';
        }

        foreach (self::UNITS as $unit => $length) {
            if ($seconds >= $length) {
                $count = intdiv($seconds, $length);

                return \sprintf('%d %s%s ago', $count, $unit, 1 === $count ? '' : 's');
            }
        }

        return 'just now';
    }
}
