<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add advanced colis fields and tracking code.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('colis')) {
            return;
        }

        $colis = $schema->getTable('colis');

        if (!$colis->hasColumn('recipient')) {
            $this->addSql('ALTER TABLE colis ADD recipient VARCHAR(255) DEFAULT NULL');
        }
        if (!$colis->hasColumn('package_option')) {
            $this->addSql('ALTER TABLE colis ADD package_option VARCHAR(255) DEFAULT NULL');
        }
        if (!$colis->hasColumn('replace_package')) {
            $this->addSql('ALTER TABLE colis ADD replace_package TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!$colis->hasColumn('old_order_number')) {
            $this->addSql('ALTER TABLE colis ADD old_order_number VARCHAR(50) DEFAULT NULL');
        }
        if (!$colis->hasColumn('carton_option')) {
            $this->addSql('ALTER TABLE colis ADD carton_option VARCHAR(20) DEFAULT NULL');
        }
        if (!$colis->hasColumn('tracking_code')) {
            $this->addSql('ALTER TABLE colis ADD tracking_code VARCHAR(50) DEFAULT NULL');
        }

        if (!$colis->hasIndex('UNIQ_9EA39CDD5A9D76B5')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_9EA39CDD5A9D76B5 ON colis (tracking_code)');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('colis')) {
            return;
        }

        $colis = $schema->getTable('colis');

        if ($colis->hasIndex('UNIQ_9EA39CDD5A9D76B5')) {
            $this->addSql('DROP INDEX UNIQ_9EA39CDD5A9D76B5 ON colis');
        }

        if ($colis->hasColumn('tracking_code')) {
            $this->addSql('ALTER TABLE colis DROP tracking_code');
        }
        if ($colis->hasColumn('carton_option')) {
            $this->addSql('ALTER TABLE colis DROP carton_option');
        }
        if ($colis->hasColumn('old_order_number')) {
            $this->addSql('ALTER TABLE colis DROP old_order_number');
        }
        if ($colis->hasColumn('replace_package')) {
            $this->addSql('ALTER TABLE colis DROP replace_package');
        }
        if ($colis->hasColumn('package_option')) {
            $this->addSql('ALTER TABLE colis DROP package_option');
        }
        if ($colis->hasColumn('recipient')) {
            $this->addSql('ALTER TABLE colis DROP recipient');
        }
    }
}
