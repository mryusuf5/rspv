<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create fonts table for user-uploaded custom fonts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fonts (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            original_filename VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            format VARCHAR(10) NOT NULL,
            file_path VARCHAR(512) NOT NULL,
            uploaded_at TIMESTAMP NOT NULL
        )');
        $this->addSql('CREATE INDEX idx_fonts_user_id ON fonts (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE fonts');
    }
}
