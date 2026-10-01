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
 *  - score = 80% skill coverage (skills asked by the position found in the CV;
 *            those listed after "Nice to have" weigh half)
 *          + 20% seniority (years of experience, capped at 8)
 *  - summary = CV headline + matched skills + missing required / nice-to-have
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
    private const float NICE_TO_HAVE_WEIGHT = 0.5;
    private const string NICE_TO_HAVE_HEADING = '/nice[- ]to[- ]have/i';

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

        [$mustHave, $niceToHave] = $this->askedSkills($position);
        $cvSkills = $this->skillsIn($cv);
        $asked = [...$mustHave, ...$niceToHave];
        $matched = array_values(array_intersect($asked, $cvSkills));
        $years = $this->yearsOfExperience($cv);

        $weightAsked = \count($mustHave) + self::NICE_TO_HAVE_WEIGHT * \count($niceToHave);
        $weightMatched = \count(array_intersect($mustHave, $cvSkills)) + self::NICE_TO_HAVE_WEIGHT * \count(array_intersect($niceToHave, $cvSkills));

        $coverage = 0.0 === $weightAsked ? 0.5 : $weightMatched / $weightAsked;
        $seniority = min($years, self::MAX_YEARS_COUNTED) / self::MAX_YEARS_COUNTED;
        $score = (int) round(80 * $coverage + 20 * $seniority);

        return CvAnalysis::create($this->summary($cv, $position, [
            'asked' => $asked,
            'matched' => $matched,
            'missingRequired' => array_values(array_diff($mustHave, $cvSkills)),
            'missingNiceToHave' => array_values(array_diff($niceToHave, $cvSkills)),
        ], $years), $score);
    }

    /**
     * Skills the position asks for, split into must-have and nice-to-have
     * (anything mentioned after a "Nice to have" heading).
     *
     * @return array{list<string>, list<string>}
     */
    private function askedSkills(Position $position): array
    {
        $parts = preg_split(self::NICE_TO_HAVE_HEADING, $position->title.' '.$position->description, 2) ?: [''];
        $mustHave = $this->skillsIn($parts[0]);
        $niceToHave = array_values(array_diff($this->skillsIn($parts[1] ?? ''), $mustHave));

        return [$mustHave, $niceToHave];
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
     * @param array{asked: list<string>, matched: list<string>, missingRequired: list<string>, missingNiceToHave: list<string>} $skills
     */
    private function summary(string $cv, Position $position, array $skills, int $years): string
    {
        $headline = rtrim(mb_substr(strtok(trim($cv), "\n") ?: 'Candidate', 0, 140), '. ').'.';
        $experience = $years > 0 ? \sprintf(' %d years of experience.', $years) : '';

        if ([] === $skills['asked']) {
            return $headline.$experience;
        }

        $fit = \sprintf(' Matches %d of %d key skills for %s', \count($skills['matched']), \count($skills['asked']), $position->title);
        $fit .= [] === $skills['matched'] ? '.' : ': '.implode(', ', $skills['matched']).'.';
        $gaps = [] === $skills['missingRequired'] ? '' : ' Missing: '.implode(', ', $skills['missingRequired']).'.';
        $gaps .= [] === $skills['missingNiceToHave'] ? '' : ' Nice to have, missing: '.implode(', ', $skills['missingNiceToHave']).'.';

        return $headline.$experience.$fit.$gaps;
    }
}
