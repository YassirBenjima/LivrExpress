<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create stock movement tables (stock entry/exit movements + items).';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('stock_movement') || $schema->hasTable('stock_movement_item')) {
            return;
        }

        $this->addSql('CREATE TABLE stock_movement (
            id INT AUTO_INCREMENT NOT NULL,
            direction VARCHAR(30) NOT NULL,
            reference VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_stock_movement_reference (reference),
            INDEX idx_stock_movement_direction (direction),
            INDEX idx_stock_movement_status (status),
            INDEX idx_stock_movement_created_at (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE stock_movement_item (
            id INT AUTO_INCREMENT NOT NULL,
            movement_id INT NOT NULL,
            variant_id INT NOT NULL,
            quantity INT NOT NULL,
            INDEX IDX_STOCK_MOVEMENT_ITEM_MOVEMENT (movement_id),
            INDEX IDX_STOCK_MOVEMENT_ITEM_VARIANT (variant_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_STOCK_MOVEMENT_ITEM_MOVEMENT FOREIGN KEY (movement_id) REFERENCES stock_movement (id) ON DELETE CASCADE,
            CONSTRAINT FK_STOCK_MOVEMENT_ITEM_VARIANT FOREIGN KEY (variant_id) REFERENCES stock_product_variant (id) ON DELETE RESTRICT
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}

