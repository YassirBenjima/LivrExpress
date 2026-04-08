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
        $user = $schema->getTable('user');
        if (!$user->hasColumn('bank_name')) {
            $this->addSql('ALTER TABLE `user` ADD bank_name VARCHAR(255) DEFAULT NULL');
        }
        if (!$user->hasColumn('bank_rib')) {
            $this->addSql('ALTER TABLE `user` ADD bank_rib VARCHAR(24) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $user = $schema->getTable('user');
        if ($user->hasColumn('bank_name')) {
            $this->addSql('ALTER TABLE `user` DROP bank_name');
        }
        if ($user->hasColumn('bank_rib')) {
            $this->addSql('ALTER TABLE `user` DROP bank_rib');
        }
    }
}
