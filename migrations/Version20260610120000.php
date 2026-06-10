<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bon_livraison and bon_livraison_colis tables.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('bon_livraison')) {
            return;
        }

        $this->addSql('CREATE TABLE bon_livraison (
            id INT AUTO_INCREMENT NOT NULL,
            created_by_id INT DEFAULT NULL,
            reference VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            registered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            UNIQUE INDEX uniq_bon_livraison_reference (reference),
            INDEX idx_bon_livraison_status (status),
            INDEX idx_bon_livraison_created_at (created_at),
            INDEX IDX_BON_LIVRAISON_CREATED_BY (created_by_id),
            CONSTRAINT FK_BON_LIVRAISON_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE bon_livraison_colis (
            bon_livraison_id INT NOT NULL,
            colis_id INT NOT NULL,
            INDEX IDX_BON_LIVRAISON_COLIS_BL (bon_livraison_id),
            INDEX IDX_BON_LIVRAISON_COLIS_COLIS (colis_id),
            PRIMARY KEY(bon_livraison_id, colis_id),
            CONSTRAINT FK_BON_LIVRAISON_COLIS_BL FOREIGN KEY (bon_livraison_id) REFERENCES bon_livraison (id) ON DELETE CASCADE,
            CONSTRAINT FK_BON_LIVRAISON_COLIS_COLIS FOREIGN KEY (colis_id) REFERENCES colis (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bon_livraison_colis');
        $this->addSql('DROP TABLE bon_livraison');
    }
}
