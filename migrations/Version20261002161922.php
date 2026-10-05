<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002161922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index on the candidate email: applications from the same address, grouped on the read side';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_job_application_candidate_email ON job_application (candidate_email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_job_application_candidate_email');
    }
}
