<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update stock_movement_item to reference variants instead of products.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('stock_movement_item')) {
            return;
        }

        $table = $schema->getTable('stock_movement_item');
        if ($table->hasColumn('variant_id')) {
            return;
        }

        // No reliable mapping from product_id to a specific variant_id.
        // Since this feature is new, we recreate the table to match the new model.
        $this->addSql('DROP TABLE stock_movement_item');

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

