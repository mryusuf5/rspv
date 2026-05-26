<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000005 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE global_books (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            title VARCHAR(500) NOT NULL,
            author VARCHAR(255) DEFAULT NULL,
            format VARCHAR(10) NOT NULL,
            total_pages INTEGER NOT NULL,
            total_words INTEGER NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            uploaded_at DATETIME NOT NULL
        )");

        $this->addSql("CREATE TABLE global_pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            global_book_id INTEGER NOT NULL,
            page_number INTEGER NOT NULL,
            content TEXT NOT NULL,
            chapter_title VARCHAR(512) DEFAULT NULL,
            word_count INTEGER NOT NULL,
            CONSTRAINT fk_global_page_book FOREIGN KEY (global_book_id) REFERENCES global_books (id) ON DELETE CASCADE
        )");

        $this->addSql("CREATE INDEX idx_global_page_book_number ON global_pages (global_book_id, page_number)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE global_pages');
        $this->addSql('DROP TABLE global_books');
    }
}
