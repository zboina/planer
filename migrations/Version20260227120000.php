<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add canvas_json column to planer_szablon_podania for Fabric.js visual editor';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_szablon_podania ADD canvas_json LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_szablon_podania DROP COLUMN canvas_json');
    }
}
