<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create return_request and return_request_colis tables.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('return_request')) {
            return;
        }

        $this->addSql('CREATE TABLE return_request (
            id INT AUTO_INCREMENT NOT NULL,
            created_by_id INT DEFAULT NULL,
            reception_type VARCHAR(255) NOT NULL,
            bon_reference VARCHAR(50) DEFAULT NULL,
            note LONGTEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            INDEX idx_return_request_status (status),
            INDEX idx_return_request_created_at (created_at),
            INDEX IDX_RETURN_REQUEST_CREATED_BY (created_by_id),
            CONSTRAINT FK_RETURN_REQUEST_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE return_request_colis (
            return_request_id INT NOT NULL,
            colis_id INT NOT NULL,
            INDEX IDX_RETURN_REQUEST_COLIS_RR (return_request_id),
            INDEX IDX_RETURN_REQUEST_COLIS_COLIS (colis_id),
            PRIMARY KEY(return_request_id, colis_id),
            CONSTRAINT FK_RETURN_REQUEST_COLIS_RR FOREIGN KEY (return_request_id) REFERENCES return_request (id) ON DELETE CASCADE,
            CONSTRAINT FK_RETURN_REQUEST_COLIS_COLIS FOREIGN KEY (colis_id) REFERENCES colis (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE return_request_colis');
        $this->addSql('DROP TABLE return_request');
    }
}
