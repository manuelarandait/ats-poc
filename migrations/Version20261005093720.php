<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005093720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AI skill-by-skill breakdown of each screened application (NULL for those screened before it existed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_application ADD ai_skills JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_application DROP ai_skills');
    }
}
