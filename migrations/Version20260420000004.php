<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000004 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD COLUMN font VARCHAR(50) DEFAULT NULL");
        $this->addSql("ALTER TABLE users ADD COLUMN theme VARCHAR(50) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN font');
        $this->addSql('ALTER TABLE users DROP COLUMN theme');
    }
}
