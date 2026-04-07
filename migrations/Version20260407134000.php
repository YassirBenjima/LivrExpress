<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407134000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bank information fields to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD bank_name VARCHAR(255) DEFAULT NULL, ADD bank_rib VARCHAR(24) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP bank_name, DROP bank_rib');
    }
}
