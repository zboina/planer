<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change podpis column from VARCHAR(255) to LONGTEXT for handwritten signature support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_podanie_urlopowe MODIFY podpis LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_podanie_urlopowe MODIFY podpis VARCHAR(255) DEFAULT NULL');
    }
}
