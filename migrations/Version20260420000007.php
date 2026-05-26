<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_active to store_font_items; add store_theme_configs table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store_font_items ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');

        $this->addSql('CREATE TABLE store_theme_configs (
            theme_id VARCHAR(50) PRIMARY KEY NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE store_theme_configs');
    }
}
