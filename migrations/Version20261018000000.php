<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shared_text (with its singleton row) and stored_file';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shared_text (id SMALLINT NOT NULL, content CLOB NOT NULL, version INTEGER DEFAULT 1 NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE stored_file (id BLOB NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(127) NOT NULL, size INTEGER NOT NULL, uploaded_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_stored_file_uploaded_at ON stored_file (uploaded_at)');
        $this->addSql("INSERT INTO shared_text (id, content, version, updated_at) VALUES (1, '', 1, datetime('now'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shared_text');
        $this->addSql('DROP TABLE stored_file');
    }
}
