<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add first_name and last_name to planer_user_profile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_user_profile ADD first_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE planer_user_profile ADD last_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planer_user_profile DROP COLUMN first_name');
        $this->addSql('ALTER TABLE planer_user_profile DROP COLUMN last_name');
    }
}
