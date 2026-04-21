<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark first two whatsapp templates as default';
    }

    public function up(Schema $schema): void
    {
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
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE whatsapp_template SET is_default = 0
            WHERE id IN (
                SELECT id FROM (
                    SELECT id FROM whatsapp_template
                    ORDER BY created_at ASC, id ASC
                    LIMIT 2
                ) first_two_templates
            )"
        );
    }
}
