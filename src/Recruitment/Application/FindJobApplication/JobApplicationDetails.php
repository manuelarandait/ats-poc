<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FindJobApplication;

use App\Recruitment\Domain\JobApplication\JobApplicationStatus;

/**
 * Everything the detail page shows, including the AI enrichment outputs.
 */
final readonly class JobApplicationDetails
{
    /**
     * @param list<ScreenedSkill>    $aiSkills          what the position asks for, required first, and whether the CV shows it
     * @param list<OtherApplication> $otherApplications from the same email, newest first
     */
    public function __construct(
        public string $id,
        public string $candidateName,
        public string $candidateEmail,
        public ?string $candidatePhone,
        public string $jobOfferId,
        public string $positionTitle,
        public string $positionDescription,
        public string $cv,
        public ?string $notes,
        public string $status,
        public string $screeningStatus,
        public ?string $aiSummary,
        public ?int $aiScore,
        public array $aiSkills,
        public \DateTimeImmutable $appliedAt,
        public ?\DateTimeImmutable $screenedAt,
        public \DateTimeImmutable $updatedAt,
        public array $otherApplications = [],
    ) {
    }

    /**
     * @return list<ScreenedSkill>
     */
    public function requiredSkills(): array
    {
        return array_values(array_filter($this->aiSkills, static fn (ScreenedSkill $skill): bool => $skill->required));
    }

    /**
     * @return list<ScreenedSkill>
     */
    public function niceToHaveSkills(): array
    {
        return array_values(array_filter($this->aiSkills, static fn (ScreenedSkill $skill): bool => !$skill->required));
    }

    /**
     * Statuses the application may move to next. The rule stays in the domain;
     * the UI only offers what the domain would accept.
     *
     * @return list<string>
     */
    public function nextStatuses(): array
    {
        return array_map(
            static fn (JobApplicationStatus $status): string => $status->value,
            JobApplicationStatus::from($this->status)->nextAllowed(),
        );
    }
}
