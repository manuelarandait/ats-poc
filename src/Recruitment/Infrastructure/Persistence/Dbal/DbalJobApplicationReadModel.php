<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Dbal;

use App\Recruitment\Application\FindJobApplication\JobApplicationDetails;
use App\Recruitment\Application\FindJobApplicationStats\JobApplicationStats;
use App\Recruitment\Application\JobApplicationReadModel;
use App\Recruitment\Application\SearchJobApplications\JobApplicationPage;
use App\Recruitment\Application\SearchJobApplications\JobApplicationSearchCriteria;
use App\Recruitment\Application\SearchJobApplications\JobApplicationSort;
use App\Recruitment\Application\SearchJobApplications\JobApplicationSummary;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Shared\Infrastructure\Persistence\Dbal\Row;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Query side: plain SQL straight into DTOs, no aggregates or unit of work.
 * Indexes backing these queries are listed in the Version20261001* migration.
 */
final readonly class DbalJobApplicationReadModel implements JobApplicationReadModel
{
    public function __construct(private Connection $connection)
    {
    }

    public function search(JobApplicationSearchCriteria $criteria): JobApplicationPage
    {
        $filtered = $this->filtered($criteria->jobOfferId, $criteria->search);

        if (null !== $criteria->status) {
            $filtered->andWhere('a.status = :status')->setParameter('status', $criteria->status->value);
        }

        $total = new Row(['total' => (clone $filtered)->select('COUNT(*)')->fetchOne()])->int('total');

        $rows = $filtered
            ->select(
                'a.id',
                'a.candidate_full_name',
                'a.candidate_email',
                'a.job_offer_id',
                'o.title AS position_title',
                'a.status',
                'a.screening_status',
                'a.ai_score',
                'a.applied_at',
            )
            ->orderBy($this->sortExpression($criteria->sort), $criteria->direction->value.(JobApplicationSort::Score === $criteria->sort ? ' NULLS LAST' : ''))
            // Ties (same name, same status…) newest first; UUID v7 is time-ordered: stable final tie-break.
            ->addOrderBy('a.applied_at', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult($criteria->offset())
            ->setMaxResults($criteria->perPage)
            ->fetchAllAssociative();

        return new JobApplicationPage(
            array_values(array_map(static function (array $values): JobApplicationSummary {
                $row = new Row($values);

                return new JobApplicationSummary(
                    $row->string('id'),
                    $row->string('candidate_full_name'),
                    $row->string('candidate_email'),
                    $row->string('job_offer_id'),
                    $row->string('position_title'),
                    $row->string('status'),
                    $row->string('screening_status'),
                    $row->nullableInt('ai_score'),
                    $row->date('applied_at'),
                );
            }, $rows)),
            $total,
            $criteria->page,
            $criteria->perPage,
            $criteria->sort,
            $criteria->direction,
        );
    }

    public function find(JobApplicationId $id): ?JobApplicationDetails
    {
        $values = $this->connection->createQueryBuilder()
            ->select('a.*', 'o.title AS position_title')
            ->from('job_application', 'a')
            ->innerJoin('a', 'job_offer', 'o', 'o.id = a.job_offer_id')
            ->where('a.id = :id')
            ->setParameter('id', $id->value)
            ->fetchAssociative();

        if (false === $values) {
            return null;
        }

        $row = new Row($values);

        return new JobApplicationDetails(
            $row->string('id'),
            $row->string('candidate_full_name'),
            $row->string('candidate_email'),
            $row->nullableString('candidate_phone'),
            $row->string('job_offer_id'),
            $row->string('position_title'),
            $row->string('cv'),
            $row->nullableString('notes'),
            $row->string('status'),
            $row->string('screening_status'),
            $row->nullableString('ai_summary'),
            $row->nullableInt('ai_score'),
            $row->date('applied_at'),
            $row->nullableDate('screened_at'),
            $row->date('updated_at'),
        );
    }

    public function stats(?JobOfferId $jobOfferId, ?string $search): JobApplicationStats
    {
        // One grouped scan; the totals are folded in PHP.
        $rows = $this->filtered($jobOfferId, $search)
            ->select(
                'a.status',
                'a.job_offer_id',
                'COUNT(*) AS applications',
                "COUNT(*) FILTER (WHERE a.screening_status = 'pending') AS analysing",
                'COUNT(a.ai_score) AS scored',
                'COALESCE(SUM(a.ai_score), 0) AS score_sum',
            )
            ->groupBy('a.status', 'a.job_offer_id')
            ->fetchAllAssociative();

        $byStatus = $byJobOffer = [];
        $analysing = $scored = $scoreSum = 0;

        foreach ($rows as $values) {
            $row = new Row($values);
            $count = $row->int('applications');
            $byStatus[$row->string('status')] = ($byStatus[$row->string('status')] ?? 0) + $count;
            $byJobOffer[$row->string('job_offer_id')] = ($byJobOffer[$row->string('job_offer_id')] ?? 0) + $count;
            $analysing += $row->int('analysing');
            $scored += $row->int('scored');
            $scoreSum += $row->int('score_sum');
        }

        return new JobApplicationStats($byStatus, $byJobOffer, $analysing, 0 === $scored ? null : (int) round($scoreSum / $scored));
    }

    /**
     * Mapped from a closed enum: no user input ever reaches the ORDER BY.
     */
    private function sortExpression(JobApplicationSort $sort): string
    {
        return match ($sort) {
            JobApplicationSort::Candidate => 'a.candidate_full_name',
            JobApplicationSort::Position => 'o.title',
            // Pipeline order (received → … → hired, then rejected), not alphabetical.
            JobApplicationSort::Status => \sprintf('CASE a.status %s END', implode(' ', array_map(
                static fn (JobApplicationStatus $status, int $position): string => \sprintf("WHEN '%s' THEN %d", $status->value, $position),
                JobApplicationStatus::cases(),
                array_keys(JobApplicationStatus::cases()),
            ))),
            JobApplicationSort::Score => 'a.ai_score',
            JobApplicationSort::AppliedAt => 'a.applied_at',
        };
    }

    private function filtered(?JobOfferId $jobOfferId, ?string $search): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->from('job_application', 'a')
            ->innerJoin('a', 'job_offer', 'o', 'o.id = a.job_offer_id');

        if (null !== $jobOfferId) {
            $query->andWhere('a.job_offer_id = :jobOfferId')->setParameter('jobOfferId', $jobOfferId->value);
        }

        if (null !== $search) {
            // Case-insensitive "contains"; served by the pg_trgm GIN indexes.
            $query
                ->andWhere('a.candidate_full_name ILIKE :search OR a.candidate_email ILIKE :search')
                ->setParameter('search', '%'.addcslashes($search, '\\%_').'%');
        }

        return $query;
    }
}
