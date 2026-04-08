<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add address column to user table';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('user')->hasColumn('address')) {
            $this->addSql('ALTER TABLE `user` ADD address VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('user')->hasColumn('address')) {
            $this->addSql('ALTER TABLE `user` DROP address');
        }
    }
}
