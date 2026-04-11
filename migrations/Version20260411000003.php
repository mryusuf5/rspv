<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user ownership and original filename to books table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE books
                ADD COLUMN user_id          INT          DEFAULT NULL,
                ADD COLUMN original_filename VARCHAR(255) NOT NULL DEFAULT ''
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE books
                ADD CONSTRAINT fk_books_user_id
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_user_book_file ON books (user_id, original_filename)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_book_file');
        $this->addSql('ALTER TABLE books DROP CONSTRAINT fk_books_user_id');
        $this->addSql('ALTER TABLE books DROP COLUMN user_id, DROP COLUMN original_filename');
    }
}
