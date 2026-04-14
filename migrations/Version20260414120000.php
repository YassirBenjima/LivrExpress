<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pickup_request table for stock product pickup requests.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('pickup_request')) {
            return;
        }

        $this->addSql('CREATE TABLE pickup_request (
            id INT AUTO_INCREMENT NOT NULL,
            product_id INT NOT NULL,
            created_by_id INT DEFAULT NULL,
            product_name_snapshot VARCHAR(255) NOT NULL,
            city VARCHAR(255) NOT NULL,
            neighborhood VARCHAR(255) NOT NULL,
            address VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            supplier_phone VARCHAR(50) DEFAULT NULL,
            note LONGTEXT DEFAULT NULL,
            has_labels TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            status VARCHAR(20) NOT NULL,
            PRIMARY KEY(id),
            INDEX idx_pickup_request_status (status),
            INDEX idx_pickup_request_created_at (created_at),
            UNIQUE INDEX uniq_pickup_request_product_status (product_id, status),
            INDEX IDX_PICKUP_REQUEST_PRODUCT (product_id),
            INDEX IDX_PICKUP_REQUEST_CREATED_BY (created_by_id),
            CONSTRAINT FK_PICKUP_REQUEST_PRODUCT FOREIGN KEY (product_id) REFERENCES stock_product (id) ON DELETE CASCADE,
            CONSTRAINT FK_PICKUP_REQUEST_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}

