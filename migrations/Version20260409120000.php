<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fragile and all_fragile flags to colis.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE colis ADD fragile TINYINT(1) DEFAULT 0 NOT NULL, ADD all_fragile TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE colis DROP fragile, DROP all_fragile');
    }
}
