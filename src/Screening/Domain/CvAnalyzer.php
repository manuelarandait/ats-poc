<?php

declare(strict_types=1);

namespace App\Screening\Domain;

/**
 * Port to the AI capability. The adapter may call an LLM (here: a mock).
 */
interface CvAnalyzer
{
    /**
     * @throws CvAnalysisUnavailable when the AI can't answer right now (worth retrying)
     */
    public function analyse(string $cv, Position $position): CvAnalysis;
}
