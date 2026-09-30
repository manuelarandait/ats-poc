<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930145051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Indexes for the applications list: ordering, filters and trigram search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_job_application_applied_at ON job_application (applied_at, id)');
        $this->addSql('CREATE INDEX idx_job_application_status ON job_application (status)');
        $this->addSql('CREATE INDEX idx_job_application_job_offer ON job_application (job_offer_id)');

        // "Contains" search (ILIKE '%term%') on name and email can't use a btree index;
        // trigram GIN indexes keep it fast as the table grows.
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql('CREATE INDEX idx_job_application_name_trgm ON job_application USING gin (candidate_full_name gin_trgm_ops)');
        $this->addSql('CREATE INDEX idx_job_application_email_trgm ON job_application USING gin (candidate_email gin_trgm_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_job_application_email_trgm');
        $this->addSql('DROP INDEX idx_job_application_name_trgm');
        $this->addSql('DROP INDEX idx_job_application_applied_at');
        $this->addSql('DROP INDEX idx_job_application_status');
        $this->addSql('DROP INDEX idx_job_application_job_offer');
    }
}
