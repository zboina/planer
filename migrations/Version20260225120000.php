<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create planer_workflow_krok and planer_user_rola tables, seed default workflow steps';
    }

    public function up(Schema $schema): void
    {
        // Workflow steps table
        $this->addSql('CREATE TABLE planer_workflow_krok (
            id SERIAL PRIMARY KEY,
            key VARCHAR(30) NOT NULL,
            label VARCHAR(100) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT \'global\',
            kolejnosc INT NOT NULL DEFAULT 0,
            CONSTRAINT uq_workflow_krok_key UNIQUE (key)
        )');

        // User roles table
        $this->addSql('CREATE TABLE planer_user_rola (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            rola VARCHAR(30) NOT NULL,
            CONSTRAINT fk_user_rola_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE,
            CONSTRAINT uq_user_rola UNIQUE (user_id, rola)
        )');
        $this->addSql('CREATE INDEX idx_user_rola_user ON planer_user_rola (user_id)');
        $this->addSql('CREATE INDEX idx_user_rola_rola ON planer_user_rola (rola)');

        // Seed default workflow steps (szef → kadry → naczelny)
        $this->addSql("INSERT INTO planer_workflow_krok (key, label, type, kolejnosc) VALUES
            ('szef', 'Szef działu', 'department', 1),
            ('kadry', 'Kadry', 'global', 2),
            ('naczelny', 'Naczelny', 'global', 3)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE planer_user_rola');
        $this->addSql('DROP TABLE planer_workflow_krok');
    }
}
