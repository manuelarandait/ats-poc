<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929093822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create job_offer and job_application tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE job_application (job_offer_id UUID NOT NULL, cv TEXT NOT NULL, notes TEXT DEFAULT NULL, applied_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, screening_status VARCHAR(20) NOT NULL, screened_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, candidate_full_name VARCHAR(150) NOT NULL, candidate_email VARCHAR(254) NOT NULL, candidate_phone VARCHAR(20) DEFAULT NULL, ai_summary TEXT DEFAULT NULL, ai_score SMALLINT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE job_offer (title VARCHAR(120) NOT NULL, description TEXT NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE job_application');
        $this->addSql('DROP TABLE job_offer');
    }
}
