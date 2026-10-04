<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * How good an AI score reads in the UI: the one place that knows the
 * thresholds, so every badge and chart colours a score the same way.
 */
final class ScoreBandExtension extends AbstractExtension
{
    private const int HIGH = 70;
    private const int MEDIUM = 40;

    public function getFilters(): array
    {
        return [new TwigFilter('score_band', $this->band(...))];
    }

    /**
     * @return 'high'|'medium'|'low'
     */
    public function band(int $score): string
    {
        return match (true) {
            $score >= self::HIGH => 'high',
            $score >= self::MEDIUM => 'medium',
            default => 'low',
        };
    }
}
