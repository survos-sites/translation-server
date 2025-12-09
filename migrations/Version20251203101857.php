<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251203101857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE str (hash VARCHAR(64) NOT NULL, original TEXT NOT NULL, src_locale VARCHAR(8) NOT NULL, context VARCHAR(128) DEFAULT NULL, meta JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(32) DEFAULT NULL, locale_statuses JSONB DEFAULT NULL, marking VARCHAR(32) DEFAULT NULL, PRIMARY KEY (hash))');
        $this->addSql('CREATE TABLE tr (meta JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(32) DEFAULT NULL, hash VARCHAR(40) NOT NULL, str_hash VARCHAR(32) NOT NULL, locale VARCHAR(8) NOT NULL, text TEXT DEFAULT NULL, marking VARCHAR(32) DEFAULT NULL, PRIMARY KEY (hash))');
        $this->addSql('CREATE INDEX IDX_B481BE1D538FF456 ON tr (str_hash)');
        $this->addSql('ALTER TABLE tr ADD CONSTRAINT FK_B481BE1D538FF456 FOREIGN KEY (str_hash) REFERENCES str (hash) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE source ADD locale_statuses JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tr DROP CONSTRAINT FK_B481BE1D538FF456');
        $this->addSql('DROP TABLE str');
        $this->addSql('DROP TABLE tr');
        $this->addSql('ALTER TABLE source DROP locale_statuses');
    }
}
