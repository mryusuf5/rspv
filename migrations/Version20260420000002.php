<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add avatar_path column to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // SQLite does not support DROP COLUMN directly; left intentionally empty
    }
}
