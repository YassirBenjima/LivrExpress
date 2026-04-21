<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421134500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split whatsapp template default flag from status and keep first two defaults';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE whatsapp_template ADD is_default TINYINT(1) NOT NULL DEFAULT 0');

        // Backfill existing "default" status rows to the new boolean flag.
        $this->addSql('UPDATE whatsapp_template SET is_default = 1 WHERE status = \'default\'');
        $this->addSql('UPDATE whatsapp_template SET status = \'active\' WHERE status = \'default\'');
        $this->addSql('UPDATE whatsapp_template SET status = \'active\' WHERE status NOT IN (\'active\', \'inactive\')');

        $this->addSql(
            "UPDATE whatsapp_template SET is_default = 1
            WHERE id = (
                SELECT id FROM (
                    SELECT id FROM whatsapp_template
                    ORDER BY created_at ASC, id ASC
                    LIMIT 1
                ) first_template
            ) AND (SELECT COUNT(*) FROM whatsapp_template WHERE is_default = 1) = 0"
        );
        $this->addSql(
            "UPDATE whatsapp_template SET is_default = 1
            WHERE id IN (
                SELECT id FROM (
                    SELECT id FROM whatsapp_template
                    ORDER BY created_at ASC, id ASC
                    LIMIT 2
                ) first_two_templates
            )"
        );

        $this->addSql('CREATE INDEX idx_whatsapp_template_is_default ON whatsapp_template (is_default)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE whatsapp_template SET status = \'default\' WHERE is_default = 1');
        $this->addSql('DROP INDEX idx_whatsapp_template_is_default ON whatsapp_template');
        $this->addSql('ALTER TABLE whatsapp_template DROP is_default');
    }
}
