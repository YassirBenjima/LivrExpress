<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408091812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Equivalent schema changes are already handled in previous migrations.
        // Keep this migration idempotent to avoid duplicate-column failures on existing databases.
        if (!$schema->hasTable('user')) {
            return;
        }

        $user = $schema->getTable('user');
        if (!$user->hasColumn('return_reception')) {
            $this->addSql('ALTER TABLE user ADD return_reception VARCHAR(255) DEFAULT NULL');
        }
        if (!$user->hasColumn('return_agency')) {
            $this->addSql('ALTER TABLE user ADD return_agency VARCHAR(255) DEFAULT NULL');
        }
        if (!$user->hasColumn('return_phone')) {
            $this->addSql('ALTER TABLE user ADD return_phone VARCHAR(255) DEFAULT NULL');
        }
        if (!$user->hasColumn('return_city')) {
            $this->addSql('ALTER TABLE user ADD return_city VARCHAR(255) DEFAULT NULL');
        }
        if (!$user->hasColumn('return_neighborhood')) {
            $this->addSql('ALTER TABLE user ADD return_neighborhood VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('user')) {
            return;
        }

        $user = $schema->getTable('user');
        if ($user->hasColumn('return_reception')) {
            $this->addSql('ALTER TABLE `user` DROP return_reception');
        }
        if ($user->hasColumn('return_agency')) {
            $this->addSql('ALTER TABLE `user` DROP return_agency');
        }
        if ($user->hasColumn('return_phone')) {
            $this->addSql('ALTER TABLE `user` DROP return_phone');
        }
        if ($user->hasColumn('return_city')) {
            $this->addSql('ALTER TABLE `user` DROP return_city');
        }
        if ($user->hasColumn('return_neighborhood')) {
            $this->addSql('ALTER TABLE `user` DROP return_neighborhood');
        }
    }
}
