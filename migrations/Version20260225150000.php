<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pomijalne flag to planer_workflow_krok';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_workflow_krok ADD pomijalne BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_workflow_krok DROP COLUMN pomijalne');
    }
}
