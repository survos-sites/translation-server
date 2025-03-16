<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250313123845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE source ALTER id TYPE INT');
        $this->addSql('ALTER TABLE source ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE source ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE target ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE target ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN target.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN target.updated_at IS \'\'');
        $this->addSql('CREATE INDEX target_marking ON target (marking)');
        $this->addSql('ALTER TABLE messenger_messages ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE messenger_messages ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE messenger_messages ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER available_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER delivered_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX target_marking');
        $this->addSql('ALTER TABLE target ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE target ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN target.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN target.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE source ALTER id TYPE INT');
        $this->addSql('ALTER TABLE source ALTER id SET DEFAULT source_id_seq');
        $this->addSql('ALTER TABLE source ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE messenger_messages ALTER id SET DEFAULT messenger_messages_id_seq');
        $this->addSql('ALTER TABLE messenger_messages ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE messenger_messages ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER available_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE messenger_messages ALTER delivered_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
