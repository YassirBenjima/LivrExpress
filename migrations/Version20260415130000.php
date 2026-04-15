<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add type, scheduled_at, assigned_driver columns to pickup_request.
 * Make product_id nullable for general (non-stock) ramassage requests.
 */
final class Version20260415130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type, scheduled_at, assigned_driver to pickup_request and make product_id nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pickup_request ADD type VARCHAR(30) NOT NULL DEFAULT \'simple\'');
        $this->addSql('ALTER TABLE pickup_request ADD scheduled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE pickup_request ADD assigned_driver VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE pickup_request MODIFY product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pickup_request DROP FOREIGN KEY IF EXISTS FK_pickup_request_product');

        // Re-add the foreign key with SET NULL on delete
        $fkName = $this->connection->createSchemaManager()->listTableForeignKeys('pickup_request');
        foreach ($fkName as $fk) {
            $localColumns = $fk->getLocalColumns();
            if (\in_array('product_id', $localColumns, true)) {
                $this->addSql(sprintf('ALTER TABLE pickup_request DROP FOREIGN KEY %s', $fk->getName()));
                break;
            }
        }

        $this->addSql(
            'ALTER TABLE pickup_request ADD CONSTRAINT FK_pickup_request_product '
            . 'FOREIGN KEY (product_id) REFERENCES stock_product (id) ON DELETE SET NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pickup_request DROP COLUMN type');
        $this->addSql('ALTER TABLE pickup_request DROP COLUMN scheduled_at');
        $this->addSql('ALTER TABLE pickup_request DROP COLUMN assigned_driver');
    }
}
