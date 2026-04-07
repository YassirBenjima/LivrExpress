<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set default value for package_option';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ALTER package_option SET DEFAULT 'Ne pas ouvrir le colis'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ALTER package_option DROP DEFAULT');
    }
}
