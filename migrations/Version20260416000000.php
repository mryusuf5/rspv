<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add total_words column to books table and backfill from pages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE books ADD COLUMN total_words INTEGER NOT NULL DEFAULT 0');

        $this->addSql(<<<'SQL'
            UPDATE books
            SET total_words = (
                SELECT COALESCE(SUM(word_count), 0)
                FROM pages
                WHERE pages.book_id = books.id
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE books DROP COLUMN total_words');
    }
}
