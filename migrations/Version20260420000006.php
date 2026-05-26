<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add store_font_items and user_purchases tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE store_font_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            format VARCHAR(10) NOT NULL,
            file_path VARCHAR(512) NOT NULL,
            uploaded_at DATETIME NOT NULL
        )');

        $this->addSql('CREATE TABLE user_purchases (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            item_type VARCHAR(20) NOT NULL,
            item_id VARCHAR(100) NOT NULL,
            purchased_at DATETIME NOT NULL,
            UNIQUE (user_id, item_type, item_id),
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE INDEX IDX_user_purchases_user ON user_purchases (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_purchases');
        $this->addSql('DROP TABLE store_font_items');
    }
}
