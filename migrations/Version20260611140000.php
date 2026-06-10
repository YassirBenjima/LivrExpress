<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add received_at to return_request table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE return_request ADD received_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE return_request DROP received_at');
    }
}
