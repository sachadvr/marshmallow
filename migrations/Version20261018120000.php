<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename shared_text to text and stored_file to file';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shared_text RENAME TO text');
        $this->addSql('ALTER TABLE stored_file RENAME TO file');
        $this->addSql('DROP INDEX idx_stored_file_uploaded_at');
        $this->addSql('CREATE INDEX idx_file_uploaded_at ON file (uploaded_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_file_uploaded_at');
        $this->addSql('ALTER TABLE file RENAME TO stored_file');
        $this->addSql('ALTER TABLE text RENAME TO shared_text');
        $this->addSql('CREATE INDEX idx_stored_file_uploaded_at ON stored_file (uploaded_at)');
    }
}
