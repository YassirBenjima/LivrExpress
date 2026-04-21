<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create whatsapp_template table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE whatsapp_template (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(150) NOT NULL, message LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT \'active\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_whatsapp_template_status (status), INDEX idx_whatsapp_template_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE whatsapp_template');
    }
}
