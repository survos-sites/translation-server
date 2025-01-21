<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250121210351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE source (hash VARCHAR(18) NOT NULL, text TEXT NOT NULL, locale VARCHAR(6) NOT NULL, PRIMARY KEY(hash))');
        $this->addSql('CREATE UNIQUE INDEX source_hash ON source (hash)');
        $this->addSql('CREATE TABLE target (key VARCHAR(32) NOT NULL, source_id VARCHAR(18) NOT NULL, target_locale VARCHAR(6) NOT NULL, engine VARCHAR(12) NOT NULL, target_text TEXT DEFAULT NULL, bing_translation TEXT DEFAULT NULL, marking VARCHAR(32) DEFAULT NULL, PRIMARY KEY(key))');
        $this->addSql('CREATE INDEX target_source ON target (source_id)');
        $this->addSql('CREATE UNIQUE INDEX target_unique_idx ON target (target_locale, source_id, engine)');
        $this->addSql('ALTER TABLE target ADD CONSTRAINT FK_466F2FFC953C1C61 FOREIGN KEY (source_id) REFERENCES source (hash) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE target DROP CONSTRAINT FK_466F2FFC953C1C61');
        $this->addSql('DROP TABLE source');
        $this->addSql('DROP TABLE target');
    }
}
