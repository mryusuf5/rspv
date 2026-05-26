<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add follows, notifications tables and is_private column to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD COLUMN is_private INTEGER NOT NULL DEFAULT 0");

        $this->addSql("CREATE TABLE follows (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            follower_id INTEGER NOT NULL,
            following_id INTEGER NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $this->addSql("CREATE UNIQUE INDEX uniq_follow ON follows (follower_id, following_id)");

        $this->addSql("CREATE TABLE notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            recipient_id INTEGER NOT NULL,
            actor_id INTEGER NOT NULL,
            type VARCHAR(50) NOT NULL,
            reference_id INTEGER DEFAULT NULL,
            created_at DATETIME NOT NULL,
            dismissed_at DATETIME DEFAULT NULL,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS notifications');
        $this->addSql('DROP TABLE IF EXISTS follows');
    }
}
