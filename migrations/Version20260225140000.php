<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sprawy column to planer_podanie_urlopowe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_podanie_urlopowe ADD sprawy TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_podanie_urlopowe DROP COLUMN sprawy');
    }
}
