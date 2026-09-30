<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * The trigram GIN indexes backing the name/email search are created by hand in
 * a migration (Doctrine mapping can't express "USING gin … gin_trgm_ops").
 * Declaring them in the generated schema stops migrations:diff from proposing
 * to drop them every time.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class TrigramSearchIndexes
{
    public const array INDEXES = [
        'idx_job_application_name_trgm' => 'candidate_full_name',
        'idx_job_application_email_trgm' => 'candidate_email',
    ];

    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();

        if (!$schema->hasTable('job_application')) {
            return;
        }

        $table = $schema->getTable('job_application');

        foreach (self::INDEXES as $name => $column) {
            if (!$table->hasIndex($name)) {
                $table->addIndex([$column], $name);
            }
        }
    }
}
