<?php

namespace Planer\PlanerBundle\Service;

class PolishHolidayService
{
    /**
     * @return array<string, string> 'Y-m-d' => nazwa
     */
    public function getPublicHolidays(int $rok): array
    {
        $easter = $this->easterDate($rok);

        return [
            sprintf('%d-01-01', $rok) => 'Nowy Rok',
            sprintf('%d-01-06', $rok) => 'Trzech Króli',
            $easter->format('Y-m-d') => 'Wielkanoc',
            (clone $easter)->modify('+1 day')->format('Y-m-d') => 'Poniedziałek Wielkanocny',
            sprintf('%d-05-01', $rok) => 'Święto Pracy',
            sprintf('%d-05-03', $rok) => 'Konstytucja 3 Maja',
            (clone $easter)->modify('+49 days')->format('Y-m-d') => 'Zielone Świątki',
            (clone $easter)->modify('+60 days')->format('Y-m-d') => 'Boże Ciało',
            sprintf('%d-08-15', $rok) => 'Wniebowzięcie NMP',
            sprintf('%d-11-01', $rok) => 'Wszystkich Świętych',
            sprintf('%d-11-11', $rok) => 'Święto Niepodległości',
            sprintf('%d-12-24', $rok) => 'Wigilia',
            sprintf('%d-12-25', $rok) => 'Boże Narodzenie',
            sprintf('%d-12-26', $rok) => 'Drugi dzień Bożego Narodzenia',
        ];
    }

    /**
     * @param array<string, string> $extraDays Firmowe dni wolne
     * @return array<string, string> Filtrowane do danego miesiąca
     */
    public function getHolidaysForMonth(int $rok, int $miesiac, array $extraDays = []): array
    {
        $prefix = sprintf('%04d-%02d-', $rok, $miesiac);
        $all = $this->getPublicHolidays($rok) + $extraDays;

        return array_filter($all, fn(string $k) => str_starts_with($k, $prefix), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Algorytm Gaussa — oblicza datę Niedzieli Wielkanocnej.
     */
    private function easterDate(int $year): \DateTime
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
