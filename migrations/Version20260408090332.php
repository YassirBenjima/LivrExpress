<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408090332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update user profile fields (return address + nullable fields) and adjust messenger delivered_at.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE user
                ADD return_reception VARCHAR(255) DEFAULT NULL,
                ADD return_agency VARCHAR(255) DEFAULT NULL,
                ADD return_phone VARCHAR(255) DEFAULT NULL,
                ADD return_city VARCHAR(255) DEFAULT NULL,
                ADD return_neighborhood VARCHAR(255) DEFAULT NULL,
                CHANGE roles roles JSON NOT NULL,
                CHANGE avatar avatar VARCHAR(255) DEFAULT NULL,
                CHANGE address address VARCHAR(255) DEFAULT NULL,
                CHANGE client_type client_type VARCHAR(255) DEFAULT NULL,
                CHANGE ice ice VARCHAR(255) DEFAULT NULL,
                CHANGE website website VARCHAR(255) DEFAULT NULL,
                CHANGE rc rc VARCHAR(255) DEFAULT NULL,
                CHANGE label_message label_message VARCHAR(255) DEFAULT NULL,
                CHANGE package_option package_option VARCHAR(255) DEFAULT NULL,
                CHANGE bank_name bank_name VARCHAR(255) DEFAULT NULL,
                CHANGE bank_rib bank_rib VARCHAR(24) DEFAULT NULL"
        );

        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
