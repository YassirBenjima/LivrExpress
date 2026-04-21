<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed first two whatsapp templates as default before status split';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE whatsapp_template SET status = \'active\' WHERE status NOT IN (\'active\', \'default\', \'inactive\')');

        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM whatsapp_template');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($count === 0) {
            $this->addSql(
                "INSERT INTO whatsapp_template (title, message, status, created_at, updated_at) VALUES
                ('Pour livreur', 'Bonjour, notre client @name veut toujours son produit @product. Merci de le contacter au @numClient pour confirmer la livraison a @address.', 'default', '{$now}', '{$now}'),
                ('Pour client', 'Bonjour @name, votre colis @product est en cours de livraison a @address. Pour toute information, contactez le livreur au @numLivreur.', 'default', '{$now}', '{$now}')"
            );

            return;
        }

        if ($count === 1) {
            $this->addSql(
                "UPDATE whatsapp_template SET status = 'default'
                WHERE id = (SELECT id FROM (SELECT id FROM whatsapp_template ORDER BY id ASC LIMIT 1) first_template)"
            );
            $this->addSql(
                "INSERT INTO whatsapp_template (title, message, status, created_at, updated_at) VALUES
                ('Pour client', 'Bonjour @name, votre colis @product est en cours de livraison a @address. Pour toute information, contactez le livreur au @numLivreur.', 'default', '{$now}', '{$now}')"
            );

            return;
        }

        $this->addSql(
            "UPDATE whatsapp_template SET status = 'active'
            WHERE status = 'default'
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM whatsapp_template ORDER BY id ASC LIMIT 2
                ) first_two_templates
            )"
        );
        $this->addSql(
            "UPDATE whatsapp_template SET status = 'default'
            WHERE id IN (
                SELECT id FROM (
                    SELECT id FROM whatsapp_template ORDER BY id ASC LIMIT 2
                ) first_two_templates
            )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE whatsapp_template SET status = \'active\' WHERE status = \'default\'');
    }
}
