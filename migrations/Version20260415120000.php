<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user JSON settings for parcel + packaging.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('user_settings')) {
            return;
        }

        // MySQL/MariaDB: JSON is supported; for older versions it maps to LONGTEXT but Doctrine handles it.
        $this->addSql('CREATE TABLE user_settings (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            parcel_settings JSON NOT NULL,
            packaging_settings JSON NOT NULL,
            UNIQUE INDEX UNIQ_USER_SETTINGS_USER (user_id),
            INDEX IDX_USER_SETTINGS_USER (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_USER_SETTINGS_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}

