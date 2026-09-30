<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Ai;

use App\Screening\Domain\CvAnalysis;
use App\Screening\Domain\CvAnalysisUnavailable;
use App\Screening\Domain\CvAnalyzer;
use App\Screening\Domain\Position;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stand-in for an LLM (the brief forbids real API calls). Deterministic and
 * explainable, yet behaves like a remote model:
 *
 *  - score = 80% skill coverage (skills asked by the position found in the CV)
 *          + 20% seniority (years of experience, capped at 8)
 *  - summary = CV headline + matched / missing skills
 *  - latency: sleeps MOCK_LLM_LATENCY_MS, so the UI shows "analysing…"
 *  - failures: MOCK_LLM_FAILURE_RATE (0–1) of calls fail at random, and a CV
 *    containing "[simulate-llm-failure]" always fails (deterministic demo/tests)
 *
 * Swapping it for a real LLM means a new CvAnalyzer adapter; nothing else changes.
 */
final readonly class FakeLlmCvAnalyzer implements CvAnalyzer
{
    public const string FAILURE_MARKER = '[simulate-llm-failure]';

    /** Canonical skill => pattern that detects it (case-insensitive). */
    private const array SKILLS = [
        'PHP' => '\bphp\b',
        'Symfony' => '\bsymfony\b',
        'Laravel' => '\blaravel\b',
        'Doctrine' => '\bdoctrine\b',
        'DDD' => '\bddd\b|domain[- ]driven',
        'Hexagonal architecture' => '\bhexagonal\b',
        'CQRS' => '\bcqrs\b',
        'Event-driven design' => 'event[- ](driven|sourcing)',
        'API Platform' => '\bapi platform\b',
        'RabbitMQ' => '\brabbitmq\b',
        'Kafka' => '\bkafka\b',
        'PostgreSQL' => '\bpostgres(ql)?\b',
        'MySQL' => '\bmysql\b',
        'Docker' => '\bdocker\b',
        'PHPUnit' => '\bphpunit\b',
        'CI/CD' => '\bci/cd\b|github actions',
        'React' => '\breact\b',
        'TypeScript' => '\btypescript\b',
        'Next.js' => '\bnext\.?js\b',
        'Tailwind' => '\btailwind\b',
        'Storybook' => '\bstorybook\b',
        'Accessibility' => '\bwcag\b|\baccessibility\b',
        'Jest' => '\bjest\b',
        'Testing Library' => '\btesting library\b',
        'Playwright' => '\bplaywright\b',
        'Python' => '\bpython\b',
        'Airflow' => '\bairflow\b',
        'dbt' => '\bdbt\b',
        'Spark' => '\bspark\b',
        'SQL' => '\bsql\b',
        'AWS' => '\baws\b',
        'Java' => '\bjava\b',
    ];

    private const int MAX_YEARS_COUNTED = 8;

    public function __construct(
        #[Autowire(env: 'int:MOCK_LLM_LATENCY_MS')]
        private int $latencyMs = 0,
        #[Autowire(env: 'float:MOCK_LLM_FAILURE_RATE')]
        private float $failureRate = 0.0,
    ) {
    }

    public function analyse(string $cv, Position $position): CvAnalysis
    {
        $this->simulateNetwork($cv);

        $required = $this->skillsIn($position->title.' '.$position->description);
        $matched = array_values(array_intersect($required, $this->skillsIn($cv)));
        $missing = array_values(array_diff($required, $matched));
        $years = $this->yearsOfExperience($cv);

        $coverage = [] === $required ? 0.5 : \count($matched) / \count($required);
        $seniority = min($years, self::MAX_YEARS_COUNTED) / self::MAX_YEARS_COUNTED;
        $score = (int) round(80 * $coverage + 20 * $seniority);

        return CvAnalysis::create($this->summary($cv, $position, $required, $matched, $missing, $years), $score);
    }

    private function simulateNetwork(string $cv): void
    {
        if ($this->latencyMs > 0) {
            usleep($this->latencyMs * 1_000);
        }

        if (str_contains($cv, self::FAILURE_MARKER)) {
            throw CvAnalysisUnavailable::because('simulated LLM failure');
        }

        if ($this->failureRate > 0 && mt_rand() / mt_getrandmax() < $this->failureRate) {
            throw CvAnalysisUnavailable::because('simulated LLM timeout');
        }
    }

    /**
     * @return list<string>
     */
    private function skillsIn(string $text): array
    {
        return array_keys(array_filter(
            self::SKILLS,
            static fn (string $pattern): bool => 1 === preg_match('~'.$pattern.'~i', $text),
        ));
    }

    private function yearsOfExperience(string $cv): int
    {
        preg_match_all('/(\d{1,2})\+?\s*years?/i', $cv, $matches);

        return [] === $matches[1] ? 0 : max(array_map(intval(...), $matches[1]));
    }

    /**
     * @param list<string> $required
     * @param list<string> $matched
     * @param list<string> $missing
     */
    private function summary(string $cv, Position $position, array $required, array $matched, array $missing, int $years): string
    {
        $headline = rtrim(mb_substr(strtok(trim($cv), "\n") ?: 'Candidate', 0, 140), '. ').'.';
        $experience = $years > 0 ? \sprintf(' %d years of experience.', $years) : '';

        if ([] === $required) {
            return $headline.$experience;
        }

        $fit = \sprintf(' Matches %d of %d key skills for %s', \count($matched), \count($required), $position->title);
        $fit .= [] === $matched ? '.' : ': '.implode(', ', $matched).'.';
        $gaps = [] === $missing ? '' : ' Missing: '.implode(', ', $missing).'.';

        return $headline.$experience.$fit.$gaps;
    }
}
