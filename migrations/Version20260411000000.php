<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create books and pages tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE books (
                id          SERIAL      NOT NULL,
                title       VARCHAR(500) NOT NULL,
                author      VARCHAR(255) DEFAULT NULL,
                format      VARCHAR(10)  NOT NULL,
                total_pages INT          NOT NULL,
                uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN books.uploaded_at IS '(DC2Type:datetime_immutable)'
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE pages (
                id          SERIAL  NOT NULL,
                book_id     INT     NOT NULL,
                page_number INT     NOT NULL,
                content     TEXT    NOT NULL,
                word_count  INT     NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX idx_page_book_number ON pages (book_id, page_number)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE pages
                ADD CONSTRAINT fk_pages_book_id
                FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pages DROP CONSTRAINT fk_pages_book_id');
        $this->addSql('DROP TABLE pages');
        $this->addSql('DROP TABLE books');
    }
}
