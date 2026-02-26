<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert elegant workflow-enabled vacation request template (Urlop z workflow)';
    }

    public function up(Schema $schema): void
    {
        $htmlFile = __DIR__ . '/../data/szablon_urlop_workflow.html';
        $html = file_get_contents($htmlFile);

        $this->addSql(
            'INSERT INTO planer_szablon_podania (nazwa, tresc_html, pola_formularza, aktywny, created_at) VALUES (:nazwa, :html, :pola, true, NOW())',
            [
                'nazwa' => 'Urlop z workflow',
                'html' => $html,
                'pola' => json_encode(['typ_podania', 'rodzaj_urlopu', 'zastepca', 'telefon', 'uzasadnienie', 'sprawy', 'podpis', 'adres']),
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM planer_szablon_podania WHERE nazwa = 'Urlop z workflow'");
    }
}
