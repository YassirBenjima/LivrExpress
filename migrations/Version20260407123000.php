<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add business profile fields to user table';
    }

    public function up(Schema $schema): void
    {
        $user = $schema->getTable('user');
        if (!$user->hasColumn('client_type')) {
            $this->addSql('ALTER TABLE `user` ADD client_type VARCHAR(255) DEFAULT NULL');
        }
        if (!$user->hasColumn('ice')) {
            $this->addSql('ALTER TABLE `user` ADD ice VARCHAR(255) DEFAULT NULL');
        }
        if (!$user->hasColumn('website')) {
            $this->addSql('ALTER TABLE `user` ADD website VARCHAR(255) DEFAULT NULL');
        }
        if (!$user->hasColumn('rc')) {
            $this->addSql('ALTER TABLE `user` ADD rc VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $user = $schema->getTable('user');
        if ($user->hasColumn('client_type')) {
            $this->addSql('ALTER TABLE `user` DROP client_type');
        }
        if ($user->hasColumn('ice')) {
            $this->addSql('ALTER TABLE `user` DROP ice');
        }
        if ($user->hasColumn('website')) {
            $this->addSql('ALTER TABLE `user` DROP website');
        }
        if ($user->hasColumn('rc')) {
            $this->addSql('ALTER TABLE `user` DROP rc');
        }
    }
}
