<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Dbal;

use App\Recruitment\Application\FindJobApplication\JobApplicationDetails;
use App\Recruitment\Application\JobApplicationReadModel;
use App\Recruitment\Application\SearchJobApplications\JobApplicationPage;
use App\Recruitment\Application\SearchJobApplications\JobApplicationSearchCriteria;
use App\Recruitment\Application\SearchJobApplications\JobApplicationSummary;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
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
        $filtered = $this->filtered($criteria);

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
            ->orderBy('a.applied_at', 'DESC')
            ->addOrderBy('a.id', 'DESC') // UUID v7 is time-ordered: stable tie-break
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

    private function filtered(JobApplicationSearchCriteria $criteria): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->from('job_application', 'a')
            ->innerJoin('a', 'job_offer', 'o', 'o.id = a.job_offer_id');

        if (null !== $criteria->status) {
            $query->andWhere('a.status = :status')->setParameter('status', $criteria->status->value);
        }

        if (null !== $criteria->jobOfferId) {
            $query->andWhere('a.job_offer_id = :jobOfferId')->setParameter('jobOfferId', $criteria->jobOfferId->value);
        }

        if (null !== $criteria->search) {
            // Case-insensitive "contains"; served by the pg_trgm GIN indexes.
            $query
                ->andWhere('a.candidate_full_name ILIKE :search OR a.candidate_email ILIKE :search')
                ->setParameter('search', '%'.addcslashes($criteria->search, '\\%_').'%');
        }

        return $query;
    }
}
