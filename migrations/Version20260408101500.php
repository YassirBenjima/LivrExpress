<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create colis table with shipping fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE colis (id INT AUTO_INCREMENT NOT NULL, order_number VARCHAR(50) NOT NULL, type VARCHAR(30) NOT NULL, city VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, phone_number VARCHAR(30) NOT NULL, neighborhood VARCHAR(255) NOT NULL, comment LONGTEXT DEFAULT NULL, product_nature VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_9EA39CDDEDD5BDF2 (order_number), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE colis');
    }
}
