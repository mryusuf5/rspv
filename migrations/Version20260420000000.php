<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate fonts table with correct auto-increment primary key (SERIAL is not valid SQLite)';
    }

    public function up(Schema $schema): void
    {
        $schema->dropTable('fonts');

        $table = $schema->createTable('fonts');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('user_id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('original_filename', Types::STRING, ['length' => 255, 'notnull' => true]);
        $table->addColumn('display_name', Types::STRING, ['length' => 255, 'notnull' => true]);
        $table->addColumn('format', Types::STRING, ['length' => 10, 'notnull' => true]);
        $table->addColumn('file_path', Types::STRING, ['length' => 512, 'notnull' => true]);
        $table->addColumn('uploaded_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addIndex(['user_id'], 'idx_fonts_user_id');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('fonts');
    }
}
