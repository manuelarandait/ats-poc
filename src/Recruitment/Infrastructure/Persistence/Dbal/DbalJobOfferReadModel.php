<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Dbal;

use App\Recruitment\Application\ListJobOffers\JobOfferReadModel;
use App\Recruitment\Application\ListJobOffers\JobOfferView;
use App\Shared\Infrastructure\Persistence\Dbal\Row;
use Doctrine\DBAL\Connection;

final readonly class DbalJobOfferReadModel implements JobOfferReadModel
{
    public function __construct(private Connection $connection)
    {
    }

    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, title, description FROM job_offer ORDER BY title');

        return array_map(static function (array $values): JobOfferView {
            $row = new Row($values);

            return new JobOfferView($row->string('id'), $row->string('title'), $row->string('description'));
        }, $rows);
    }
}
