<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413111500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add qr_code_path to stock_product.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('stock_product')) {
            return;
        }

        $table = $schema->getTable('stock_product');
        if ($table->hasColumn('qr_code_path')) {
            return;
        }

        $this->addSql('ALTER TABLE stock_product ADD qr_code_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}

