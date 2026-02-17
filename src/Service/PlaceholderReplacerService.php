<?php

namespace Planer\PlanerBundle\Service;

use Planer\PlanerBundle\Entity\PlanerUstawienia;
use Planer\PlanerBundle\Entity\PodanieUrlopowe;

class PlaceholderReplacerService
{
    /**
     * Replace all [[PLACEHOLDER]] tokens with real data from the podanie.
     */
    public function replace(string $html, PodanieUrlopowe $podanie, PlanerUstawienia $settings): string
    {
        $user = $podanie->getUser();

        $adresHtml = '';
        if ($user->getAdres()) {
            $adresHtml = implode('<br>', array_map(
                fn(string $line) => htmlspecialchars($line, ENT_QUOTES, 'UTF-8'),
                explode("\n", $user->getAdres())
            ));
        }

        $firmaAdresHtml = '';
        if ($settings->getFirmaAdres()) {
            $firmaAdresHtml = implode('<br>', array_map(
                fn(string $line) => htmlspecialchars($line, ENT_QUOTES, 'UTF-8'),
                explode("\n", $settings->getFirmaAdres())
            ));
        }

        // Build podpis HTML
        $podpisHtml = '';
        if ($podanie->getPodpis()) {
            $podpisHtml = '<span style="font-style:italic;">' . htmlspecialchars($podanie->getPodpis(), ENT_QUOTES, 'UTF-8') . '</span>';
        } else {
            $podpisHtml = '<span style="border-bottom:1px dotted #999;display:inline-block;width:200px;height:14px;"></span>';
        }

        // Build typ podania with strikethrough
        $typPodaniaSkreslenie = $this->buildTypPodaniaSkreslenie($podanie);
        $urlopCzasSkreslenie = $this->buildUrlopCzasSkreslenie($podanie);

        $replacements = [
            '[[IMIE_NAZWISKO]]' => htmlspecialchars($user->getFullName(), ENT_QUOTES, 'UTF-8'),
            '[[ADRES]]' => $adresHtml,
            '[[DATA_OD]]' => $podanie->getDataOd()->format('d.m.Y'),
            '[[DATA_DO]]' => $podanie->getDataDo()->format('d.m.Y'),
            '[[DATA_ZLOZENIA]]' => $podanie->getCreatedAt()->format('d.m.Y'),
            '[[FIRMA_NAZWA]]' => htmlspecialchars($settings->getFirmaNazwa(), ENT_QUOTES, 'UTF-8'),
            '[[FIRMA_ADRES]]' => $firmaAdresHtml,
            '[[ZASTEPCA]]' => htmlspecialchars($podanie->getZastepca() ?? '', ENT_QUOTES, 'UTF-8'),
            '[[TELEFON]]' => htmlspecialchars($podanie->getTelefon() ?? '', ENT_QUOTES, 'UTF-8'),
            '[[UZASADNIENIE]]' => htmlspecialchars($podanie->getUzasadnienie() ?? '', ENT_QUOTES, 'UTF-8'),
            '[[PODPIS]]' => $podpisHtml,
            '[[TYP_PODANIA_SKRESLENIE]]' => $typPodaniaSkreslenie,
            '[[URLOP_CZAS_SKRESLENIE]]' => $urlopCzasSkreslenie,
            '[[RODZAJ_URLOPU]]' => htmlspecialchars($podanie->getRodzajUrlopu()?->getNazwa() ?? 'wypoczynkowego', ENT_QUOTES, 'UTF-8'),
            '[[DEPARTAMENT]]' => htmlspecialchars($podanie->getDepartament()->getNazwa(), ENT_QUOTES, 'UTF-8'),
            '[[ROK]]' => $podanie->getDataOd()->format('Y'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /**
     * Replace placeholders with sample/preview data.
     */
    public function replaceWithSampleData(string $html, PlanerUstawienia $settings): string
    {
        $firmaAdresHtml = '';
        if ($settings->getFirmaAdres()) {
            $firmaAdresHtml = implode('<br>', array_map(
                fn(string $line) => htmlspecialchars($line, ENT_QUOTES, 'UTF-8'),
                explode("\n", $settings->getFirmaAdres())
            ));
        } else {
            $firmaAdresHtml = 'ul. Firmowa 10<br>00-001 Warszawa';
        }

        $samplePodpis = '<span style="font-style:italic;">Jan Kowalski</span>';

        $sampleTypPodania = '<strong>urlopu</strong>, <span style="text-decoration:line-through;">czasu wolnego od pracy</span>';

        $replacements = [
            '[[IMIE_NAZWISKO]]' => 'Jan Kowalski',
            '[[ADRES]]' => 'ul. Przykładowa 1<br>00-001 Warszawa',
            '[[DATA_OD]]' => '01.03.2026',
            '[[DATA_DO]]' => '05.03.2026',
            '[[DATA_ZLOZENIA]]' => date('d.m.Y'),
            '[[FIRMA_NAZWA]]' => htmlspecialchars($settings->getFirmaNazwa() ?: 'Przykładowa Firma Sp. z o.o.', ENT_QUOTES, 'UTF-8'),
            '[[FIRMA_ADRES]]' => $firmaAdresHtml,
            '[[ZASTEPCA]]' => 'Anna Nowak',
            '[[TELEFON]]' => '600 123 456',
            '[[UZASADNIENIE]]' => 'Sprawy rodzinne',
            '[[PODPIS]]' => $samplePodpis,
            '[[TYP_PODANIA_SKRESLENIE]]' => $sampleTypPodania,
            '[[URLOP_CZAS_SKRESLENIE]]' => 'urlopu(*), <span style="text-decoration:line-through;">czasu wolnego od pracy(*)</span>',
            '[[RODZAJ_URLOPU]]' => 'wypoczynkowego',
            '[[DEPARTAMENT]]' => 'Dział IT',
            '[[ROK]]' => date('Y'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /**
     * Returns a reference of available placeholders with descriptions.
     *
     * @return array<string, string>
     */
    public static function getPlaceholderReference(): array
    {
        return [
            '[[IMIE_NAZWISKO]]' => 'Imię i nazwisko pracownika',
            '[[ADRES]]' => 'Adres zamieszkania (z <br>)',
            '[[DATA_OD]]' => 'Data od (dd.mm.YYYY)',
            '[[DATA_DO]]' => 'Data do (dd.mm.YYYY)',
            '[[DATA_ZLOZENIA]]' => 'Data złożenia (dd.mm.YYYY)',
            '[[FIRMA_NAZWA]]' => 'Nazwa firmy z ustawień',
            '[[FIRMA_ADRES]]' => 'Adres firmy (z <br>)',
            '[[ZASTEPCA]]' => 'Zastępca',
            '[[TELEFON]]' => 'Telefon kontaktowy',
            '[[UZASADNIENIE]]' => 'Uzasadnienie (opcjonalne)',
            '[[PODPIS]]' => 'Podpis (tekst lub pusta linia)',
            '[[TYP_PODANIA_SKRESLENIE]]' => 'Typ podania z skreśleniami (HTML)',
            '[[URLOP_CZAS_SKRESLENIE]]' => 'urlopu/czasu wolnego — z skreśleniem (HTML)',
            '[[RODZAJ_URLOPU]]' => 'Rodzaj urlopu',
            '[[DEPARTAMENT]]' => 'Nazwa departamentu',
            '[[ROK]]' => 'Rok (z daty od)',
        ];
    }

    /**
     * Build HTML for typ podania with strikethrough — matching the original Twig logic.
     */
    private function buildTypPodaniaSkreslenie(PodanieUrlopowe $podanie): string
    {
        $typPodania = $podanie->getTypPodania();
        $typy = ['urlopu', 'czasu wolnego od pracy'];

        if (!$typPodania) {
            return 'urlopu(*), <span style="text-decoration:line-through;">czasu wolnego od pracy(*)</span>';
        }

        $parts = [];
        foreach ($typy as $i => $t) {
            if ($t === $typPodania->getNazwa()) {
                $parts[] = '<strong>' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</strong>';
            } else {
                $parts[] = '<span style="text-decoration:line-through;">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</span>';
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Build "urlopu(*), czasu wolnego od pracy(*)" with strikethrough on the unselected one.
     */
    private function buildUrlopCzasSkreslenie(PodanieUrlopowe $podanie): string
    {
        $typPodania = $podanie->getTypPodania();
        $nazwa = $typPodania?->getNazwa();

        $urlop = 'urlopu(*)';
        $czasWolny = 'czasu wolnego od pracy(*)';

        if ($nazwa === 'czasu wolnego od pracy') {
            return '<span style="text-decoration:line-through;">' . $urlop . '</span>, ' . $czasWolny;
        }

        // Default: urlop selected (or no selection)
        return $urlop . ', <span style="text-decoration:line-through;">' . $czasWolny . '</span>';
    }
}
