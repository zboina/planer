<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tworzy tabelę dzien_wolny_firmy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dzien_wolny_firmy (
            id SERIAL PRIMARY KEY,
            data DATE NOT NULL,
            nazwa VARCHAR(100) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
            CONSTRAINT uq_dzien_wolny_firmy_data UNIQUE (data)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dzien_wolny_firmy');
    }
}
