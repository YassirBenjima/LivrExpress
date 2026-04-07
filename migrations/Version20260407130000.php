<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add delivery preference fields to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD label_message VARCHAR(255) DEFAULT NULL, ADD package_option VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP label_message, DROP package_option');
    }
}
