<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210134529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX target_unique_idx');
        $this->addSql('ALTER TABLE target ALTER engine SET DEFAULT \'libre\'');
        $this->addSql('ALTER TABLE target ALTER engine SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX target_unique_idx ON target (target_locale, source_id, engine)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX target_unique_idx');
        $this->addSql('ALTER TABLE target ALTER engine DROP DEFAULT');
        $this->addSql('ALTER TABLE target ALTER engine DROP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX target_unique_idx ON target (target_locale, source_id)');
    }
}
