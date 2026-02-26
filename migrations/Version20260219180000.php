<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dodaje kolumny workflow do planer_podanie_urlopowe (status, status_zmieniony_at, status_przez_id, komentarz_odrzucenia)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_podanie_urlopowe ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT \'zlozony\'');
        $this->addSql('ALTER TABLE planer_podanie_urlopowe ADD COLUMN status_zmieniony_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE planer_podanie_urlopowe ADD COLUMN status_przez_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE planer_podanie_urlopowe ADD COLUMN komentarz_odrzucenia VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE planer_podanie_urlopowe ADD CONSTRAINT fk_podanie_status_przez FOREIGN KEY (status_przez_id) REFERENCES "user" (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_podanie_urlopowe DROP CONSTRAINT IF EXISTS fk_podanie_status_przez');
        $this->addSql('ALTER TABLE planer_podanie_urlopowe DROP COLUMN status');
        $this->addSql('ALTER TABLE planer_podanie_urlopowe DROP COLUMN status_zmieniony_at');
        $this->addSql('ALTER TABLE planer_podanie_urlopowe DROP COLUMN status_przez_id');
        $this->addSql('ALTER TABLE planer_podanie_urlopowe DROP COLUMN komentarz_odrzucenia');
    }
}
