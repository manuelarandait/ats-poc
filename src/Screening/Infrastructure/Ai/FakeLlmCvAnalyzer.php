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
 *  - summary = the candidate's profile (role, years of experience, main
 *            skills in the order the CV lists them), then the fit for the
 *            position (matched skills, missing required / nice-to-have)
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
        'JavaScript' => '\bjavascript\b',
        'Node.js' => '\bnode(\.?js)?\b',
        'Vue.js' => '\bvue(\.?js)?\b',
        'Angular' => '\bangular\b',
        'GraphQL' => '\bgraphql\b',
        'Redis' => '\bredis\b',
        'MongoDB' => '\bmongo(db)?\b',
        'Kubernetes' => '\bkubernetes\b|\bk8s\b',
    ];

    /** A line naming a job title, in English or Spanish (CVs are pasted as they are). */
    private const string ROLE_PATTERN = '/\b(engineer|developer|programmer|architect|lead|manager|analyst|scientist|designer|consultant|cto|devops|desarrollador|desarrolladora|ingeniero|ingeniera|programador|programadora|arquitecto|arquitecta)\b/iu';
    private const int ROLE_LINES_SCANNED = 6;
    private const int ROLE_MAX_LENGTH = 60;
    private const int MAIN_SKILLS_SHOWN = 5;

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
        preg_match_all('/(\d{1,2})\+?\s*(?:years?|años)\b/iu', $cv, $matches);

        return [] === $matches[1] ? 0 : max(array_map(intval(...), $matches[1]));
    }

    /**
     * @param array{asked: list<string>, matched: list<string>, missingRequired: list<string>, missingNiceToHave: list<string>} $skills
     */
    private function summary(string $cv, Position $position, array $skills, int $years): string
    {
        $profile = $this->profile($cv, $years);

        if ([] === $skills['asked']) {
            return $profile;
        }

        $fit = \sprintf(' Matches %d of %d key skills for %s', \count($skills['matched']), \count($skills['asked']), $position->title);
        $fit .= [] === $skills['matched'] ? '.' : ': '.implode(', ', $skills['matched']).'.';
        $gaps = [] === $skills['missingRequired'] ? '' : ' Missing: '.implode(', ', $skills['missingRequired']).'.';
        $gaps .= [] === $skills['missingNiceToHave'] ? '' : ' Nice to have, missing: '.implode(', ', $skills['missingNiceToHave']).'.';

        return $profile.$fit.$gaps;
    }

    /**
     * Who the candidate is, from the CV alone: "Backend engineer with 7 years
     * of experience. Main skills: PHP, Symfony, Doctrine (+2 more).".
     */
    private function profile(string $cv, int $years): string
    {
        $role = $this->role($cv);
        $mainSkills = $this->skillsByAppearance($cv);

        if (null === $role && 0 === $years && [] === $mainSkills) {
            return 'The CV gives too little detail to describe the candidate\'s profile.';
        }

        $parts = [];

        if (null !== $role || $years > 0) {
            $parts[] = $years > 0 ? \sprintf('%s with %d years of experience.', $role ?? 'Candidate', $years) : $role.'.';
        }

        if ([] !== $mainSkills) {
            $more = \count($mainSkills) - self::MAIN_SKILLS_SHOWN;
            $parts[] = 'Main skills: '.implode(', ', \array_slice($mainSkills, 0, self::MAIN_SKILLS_SHOWN)).($more > 0 ? \sprintf(' (+%d more)', $more) : '').'.';
        }

        return implode(' ', $parts);
    }

    /**
     * The job title from the CV's first lines (the very first one is often the
     * name): "Senior Full-Stack Engineer (Symfony / Vue) - Freelance" →
     * "Senior Full-Stack Engineer".
     */
    private function role(string $cv): ?string
    {
        $lines = \array_slice(array_values(array_filter(array_map(trim(...), explode("\n", $cv)))), 0, self::ROLE_LINES_SCANNED);

        foreach ($lines as $line) {
            if (1 !== preg_match(self::ROLE_PATTERN, $line)) {
                continue;
            }

            $role = (string) preg_replace('/\s*\([^)]*\)/u', '', $line);
            $role = trim((preg_split('/\s+(?:at|en|@|[-–—|])\s+|[,.;:]/u', $role, 2) ?: [''])[0], " -–—|\t");

            if ('' !== $role && mb_strlen($role) <= self::ROLE_MAX_LENGTH) {
                return mb_ucfirst($role);
            }
        }

        return null;
    }

    /**
     * Every known skill in the text, in the order it first appears (what the
     * candidate leads with is usually what they know best).
     *
     * @return list<string>
     */
    private function skillsByAppearance(string $text): array
    {
        $found = [];

        foreach (self::SKILLS as $skill => $pattern) {
            if (1 === preg_match('~'.$pattern.'~i', $text, $match, \PREG_OFFSET_CAPTURE)) {
                $found[$skill] = $match[0][1];
            }
        }

        asort($found);

        return array_keys($found);
    }
}
