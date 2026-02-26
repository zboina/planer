<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add podglada_ids JSON column to planer_departament';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE planer_departament ADD podglada_ids JSON NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_departament DROP COLUMN podglada_ids');
    }
}
