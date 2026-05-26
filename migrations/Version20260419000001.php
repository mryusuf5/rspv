<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_badges table for server-side badge persistence';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('user_badges');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('user_id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('badge_id', Types::STRING, ['length' => 100, 'notnull' => true]);
        $table->addColumn('earned_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['user_id', 'badge_id'], 'uniq_user_badge');
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addIndex(['user_id'], 'idx_user_badges_user_id');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('user_badges');
    }
}
