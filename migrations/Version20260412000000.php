<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add chapter_title column to pages table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE pages ADD COLUMN chapter_title VARCHAR(512) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pages DROP COLUMN chapter_title');
    }
}
