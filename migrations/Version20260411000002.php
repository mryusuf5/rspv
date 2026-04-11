<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_book_progress table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_book_progress (
                id          SERIAL  NOT NULL,
                user_id     INT     NOT NULL,
                book_id     INT     NOT NULL,
                page_number INT     NOT NULL,
                word_index  INT     NOT NULL,
                updated_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN user_book_progress.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_user_book ON user_book_progress (user_id, book_id)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE user_book_progress
                ADD CONSTRAINT fk_progress_user_id
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE user_book_progress
                ADD CONSTRAINT fk_progress_book_id
                FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_book_progress DROP CONSTRAINT fk_progress_user_id');
        $this->addSql('ALTER TABLE user_book_progress DROP CONSTRAINT fk_progress_book_id');
        $this->addSql('DROP TABLE user_book_progress');
    }
}
