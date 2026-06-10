<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create crbt table and add payment fields on colis.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE colis ADD payment_type VARCHAR(20) DEFAULT 'CRBT' NOT NULL, ADD delivery_fee NUMERIC(10, 2) DEFAULT NULL");
        $this->addSql('CREATE TABLE crbt (
            id INT AUTO_INCREMENT NOT NULL,
            colis_id INT NOT NULL,
            reference VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            frais NUMERIC(10, 2) NOT NULL,
            montant_frais NUMERIC(10, 2) NOT NULL,
            montant NUMERIC(10, 2) NOT NULL,
            balance NUMERIC(10, 2) NOT NULL,
            UNIQUE INDEX uniq_crbt_reference (reference),
            UNIQUE INDEX uniq_crbt_colis (colis_id),
            INDEX idx_crbt_status (status),
            INDEX idx_crbt_created_at (created_at),
            PRIMARY KEY(id),
            CONSTRAINT FK_CRBT_COLIS FOREIGN KEY (colis_id) REFERENCES colis (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE crbt');
        $this->addSql('ALTER TABLE colis DROP payment_type, DROP delivery_fee');
    }
}
