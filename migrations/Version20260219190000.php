<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create planer_podanie_log table for workflow history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE planer_podanie_log (
            id SERIAL PRIMARY KEY,
            podanie_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            transition VARCHAR(30) NOT NULL,
            from_status VARCHAR(20) NOT NULL,
            to_status VARCHAR(20) NOT NULL,
            komentarz VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            CONSTRAINT fk_podanie_log_podanie FOREIGN KEY (podanie_id) REFERENCES planer_podanie_urlopowe (id) ON DELETE CASCADE,
            CONSTRAINT fk_podanie_log_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL
        )');
        $this->addSql('COMMENT ON COLUMN planer_podanie_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_podanie_log_podanie ON planer_podanie_log (podanie_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE planer_podanie_log');
    }
}
