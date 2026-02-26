<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tworzy tabelę planer_modul (system modułów z trybami dostępu)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE planer_modul (
            id SERIAL PRIMARY KEY,
            kod VARCHAR(50) NOT NULL,
            nazwa VARCHAR(100) NOT NULL,
            opis TEXT DEFAULT NULL,
            ikona VARCHAR(50) DEFAULT NULL,
            aktywny BOOLEAN NOT NULL DEFAULT TRUE,
            kolejnosc INT NOT NULL DEFAULT 0,
            tryb_dostepu VARCHAR(20) NOT NULL DEFAULT \'wszyscy\',
            dozwolone_role JSON NOT NULL DEFAULT \'[]\',
            dozwoleni_user_ids JSON NOT NULL DEFAULT \'[]\',
            dozwolone_departamenty_ids JSON NOT NULL DEFAULT \'[]\',
            CONSTRAINT uq_planer_modul_kod UNIQUE (kod)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE planer_modul');
    }
}
