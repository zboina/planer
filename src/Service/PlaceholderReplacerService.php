<?php

namespace Planer\PlanerBundle\Service;

use Planer\PlanerBundle\Entity\PlanerUstawienia;
use Planer\PlanerBundle\Entity\PodanieLog;
use Planer\PlanerBundle\Entity\PodanieUrlopowe;
use Planer\PlanerBundle\Repository\PodanieLogRepository;

class PlaceholderReplacerService
{
    public function __construct(
        private PlanerUserResolver $userResolver,
        private PodanieLogRepository $logRepo,
        private PodanieWorkflowFactory $workflowFactory,
        private PodanieStatusProvider $statusProvider,
    ) {
    }

    /**
     * Replace all [[PLACEHOLDER]] tokens with real data from the podanie.
     */
    public function replace(string $html, PodanieUrlopowe $podanie, PlanerUstawienia $settings): string
    {
        $user = $podanie->getUser();

        $adresHtml = '';
        $adres = $this->userResolver->getAdres($user);
        if ($adres) {
            $adresHtml = implode('<br>', array_map(
                fn(string $line) => htmlspecialchars($line, ENT_QUOTES, 'UTF-8'),
                explode("\n", $adres)
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
        $podpisValue = $podanie->getPodpis();
        if ($podpisValue && str_starts_with($podpisValue, 'data:image/')) {
            // Handwritten signature — render as image
            $podpisHtml = '<img src="' . $podpisValue . '" style="height:40px;max-width:200px;" alt="Podpis">';
        } elseif ($podpisValue) {
            $podpisHtml = '<span style="font-style:italic;">' . htmlspecialchars($podpisValue, ENT_QUOTES, 'UTF-8') . '</span>';
        } else {
            $podpisHtml = '<span style="border-bottom:1px dotted #999;display:inline-block;width:200px;height:14px;"></span>';
        }

        // Build typ podania with strikethrough
        $typPodaniaSkreslenie = $this->buildTypPodaniaSkreslenie($podanie);
        $urlopCzasSkreslenie = $this->buildUrlopCzasSkreslenie($podanie);

        // Workflow data
        $logs = $this->logRepo->findByPodanie($podanie);
        $steps = $this->workflowFactory->getActiveSteps();
        $status = $podanie->getStatus();

        // Days count
        $diff = $podanie->getDataDo()->diff($podanie->getDataOd());
        $days = $diff->days + 1;

        // Conditional uzasadnienie row
        $uzasadnienie = $podanie->getUzasadnienie();
        $wierszUzasadnienie = '';
        if ($uzasadnienie !== null && trim($uzasadnienie) !== '') {
            $wierszUzasadnienie = '<tr><td class="label">Uzasadnienie</td><td class="value">'
                . htmlspecialchars($uzasadnienie, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }

        // Sprawy do załatwienia
        $sprawy = $podanie->getSprawy();
        $sprawyHtml = '';
        if ($sprawy !== null && trim($sprawy) !== '') {
            $sprawyHtml = htmlspecialchars($sprawy, ENT_QUOTES, 'UTF-8');
        }
        $sekcjaSprawyHtml = $this->buildSekcjaSprawyHtml($sprawy);

        $replacements = [
            '[[IMIE_NAZWISKO]]' => htmlspecialchars($this->userResolver->getFullName($user), ENT_QUOTES, 'UTF-8'),
            '[[ADRES]]' => $adresHtml,
            '[[DATA_OD]]' => $podanie->getDataOd()->format('d.m.Y'),
            '[[DATA_DO]]' => $podanie->getDataDo()->format('d.m.Y'),
            '[[DATA_ZLOZENIA]]' => $podanie->getCreatedAt()->format('d.m.Y'),
            '[[FIRMA_NAZWA]]' => htmlspecialchars($settings->getFirmaNazwa(), ENT_QUOTES, 'UTF-8'),
            '[[FIRMA_ADRES]]' => $firmaAdresHtml,
            '[[ZASTEPCA]]' => htmlspecialchars($podanie->getZastepca() ?? '', ENT_QUOTES, 'UTF-8'),
            '[[TELEFON]]' => htmlspecialchars($podanie->getTelefon() ?? '', ENT_QUOTES, 'UTF-8'),
            '[[UZASADNIENIE]]' => htmlspecialchars($uzasadnienie ?? '', ENT_QUOTES, 'UTF-8'),
            '[[WIERSZ_UZASADNIENIE]]' => $wierszUzasadnienie,
            '[[SPRAWY]]' => $sprawyHtml,
            '[[SEKCJA_SPRAWY]]' => $sekcjaSprawyHtml,
            '[[PODPIS]]' => $podpisHtml,
            '[[TYP_PODANIA_SKRESLENIE]]' => $typPodaniaSkreslenie,
            '[[URLOP_CZAS_SKRESLENIE]]' => $urlopCzasSkreslenie,
            '[[RODZAJ_URLOPU]]' => htmlspecialchars($podanie->getRodzajUrlopu()?->getNazwa() ?? 'wypoczynkowego', ENT_QUOTES, 'UTF-8'),
            '[[DEPARTAMENT]]' => htmlspecialchars($podanie->getDepartament()->getNazwa(), ENT_QUOTES, 'UTF-8'),
            '[[ROK]]' => $podanie->getDataOd()->format('Y'),
            '[[NR_PODANIA]]' => (string) $podanie->getId(),
            '[[LICZBA_DNI]]' => $days . ' ' . ($days === 1 ? 'dzień' : 'dni'),
            '[[STATUS]]' => htmlspecialchars($this->statusProvider->getLabel($status), ENT_QUOTES, 'UTF-8'),
            '[[WORKFLOW_STATUS]]' => $this->buildWorkflowStatusHtml($status),
            '[[WORKFLOW_CHAIN]]' => $this->buildWorkflowChainHtml($status, $steps, $logs),
            '[[WORKFLOW_APPROVALS]]' => $this->buildWorkflowApprovalsHtml($status, $steps, $logs),
            '[[WORKFLOW_HISTORIA]]' => $this->buildWorkflowHistoriaHtml($podanie, $logs),
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
            '[[WIERSZ_UZASADNIENIE]]' => '<tr><td class="label">Uzasadnienie</td><td class="value">Sprawy rodzinne</td></tr>',
            '[[SPRAWY]]' => 'Spotkanie z klientem ABC — 03.03, termin faktury nr 12/2026 — 04.03',
            '[[SEKCJA_SPRAWY]]' => $this->buildSekcjaSprawyHtml('Spotkanie z klientem ABC — 03.03, termin faktury nr 12/2026 — 04.03'),
            '[[PODPIS]]' => $samplePodpis,
            '[[TYP_PODANIA_SKRESLENIE]]' => $sampleTypPodania,
            '[[URLOP_CZAS_SKRESLENIE]]' => 'urlopu(*), <span style="text-decoration:line-through;">czasu wolnego od pracy(*)</span>',
            '[[RODZAJ_URLOPU]]' => 'wypoczynkowego',
            '[[DEPARTAMENT]]' => 'Dział IT',
            '[[ROK]]' => date('Y'),
            '[[NR_PODANIA]]' => '42',
            '[[LICZBA_DNI]]' => '5 dni',
            '[[STATUS]]' => 'Złożony',
            '[[WORKFLOW_STATUS]]' => '',
            '[[WORKFLOW_CHAIN]]' => $this->buildSampleWorkflowChainHtml(),
            '[[WORKFLOW_APPROVALS]]' => $this->buildSampleWorkflowApprovalsHtml(),
            '[[WORKFLOW_HISTORIA]]' => $this->buildSampleWorkflowHistoriaHtml(),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /**
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
            '[[PODPIS]]' => 'Podpis (tekst, obrazek odręczny lub pusta linia)',
            '[[TYP_PODANIA_SKRESLENIE]]' => 'Typ podania z skreśleniami (HTML)',
            '[[URLOP_CZAS_SKRESLENIE]]' => 'urlopu/czasu wolnego — z skreśleniem (HTML)',
            '[[RODZAJ_URLOPU]]' => 'Rodzaj urlopu',
            '[[DEPARTAMENT]]' => 'Nazwa departamentu',
            '[[ROK]]' => 'Rok (z daty od)',
            '[[WIERSZ_UZASADNIENIE]]' => 'Wiersz uzasadnienia (ukryty gdy puste)',
            '[[SPRAWY]]' => 'Sprawy do załatwienia podczas nieobecności',
            '[[SEKCJA_SPRAWY]]' => 'Sekcja spraw z auto-skreśleniem (HTML)',
            '[[NR_PODANIA]]' => 'Numer (ID) podania',
            '[[LICZBA_DNI]]' => 'Liczba dni (np. "5 dni")',
            '[[STATUS]]' => 'Aktualny status podania',
            '[[WORKFLOW_STATUS]]' => 'Baner statusu ZATWIERDZONY/ODRZUCONY (HTML)',
            '[[WORKFLOW_CHAIN]]' => 'Wizualny łańcuch workflow (HTML)',
            '[[WORKFLOW_APPROVALS]]' => 'Siatka akceptacji z podpisami (HTML)',
            '[[WORKFLOW_HISTORIA]]' => 'Tabela historii zmian statusu (HTML)',
        ];
    }

    private function buildSekcjaSprawyHtml(?string $sprawy): string
    {
        $hasSprawy = $sprawy !== null && trim($sprawy) !== '';

        $html = '<div style="margin-top:6px;font-size:8.5pt;color:#333;">';
        $html .= 'W okresie mojej nieobecności: ';

        if ($hasSprawy) {
            // Są sprawy — skreślamy "nie powinno być..."
            $html .= '<span style="text-decoration:line-through;color:#aaa;">nie powinno być spraw wymagających pilnego załatwienia(*)</span>, ';
            $html .= 'następujące sprawy wymagają pilnego załatwienia(*)';
            $html .= '</div>';
            $html .= '<div style="margin-top:3px;font-size:8.5pt;">' . htmlspecialchars($sprawy, ENT_QUOTES, 'UTF-8') . '</div>';
        } else {
            // Brak spraw — skreślamy "następujące sprawy..."
            $html .= 'nie powinno być spraw wymagających pilnego załatwienia(*), ';
            $html .= '<span style="text-decoration:line-through;color:#aaa;">następujące sprawy wymagają pilnego załatwienia(*)</span>';
            $html .= '</div>';
        }

        return $html;
    }

    private function buildWorkflowStatusHtml(string $status): string
    {
        if ($status === 'zatwierdzony') {
            return '<div style="text-align:center;margin:10px 0;padding:6px;border:2.5px solid #333;font-size:14pt;font-weight:bold;letter-spacing:2px;color:#111;">ZATWIERDZONY</div>';
        }
        if ($status === 'odrzucony') {
            return '<div style="text-align:center;margin:10px 0;padding:6px;border:2.5px solid #333;font-size:14pt;font-weight:bold;letter-spacing:2px;color:#111;">&#10007; ODRZUCONY</div>';
        }
        if ($status === 'anulowany') {
            return '<div style="text-align:center;margin:10px 0;padding:6px;border:2.5px solid #333;font-size:14pt;font-weight:bold;letter-spacing:2px;color:#111;">ANULOWANY</div>';
        }
        // Active statuses — show current step
        $nextStep = $this->statusProvider->getNextStepLabel($status);
        if ($nextStep) {
            return '<div style="text-align:center;margin:10px 0;padding:5px;border:1px solid #999;font-size:9pt;color:#333;">Status: <strong>' . htmlspecialchars($this->statusProvider->getLabel($status), ENT_QUOTES, 'UTF-8') . '</strong> &mdash; oczekuje na: <strong>' . htmlspecialchars($nextStep, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        }
        return '';
    }

    /**
     * B&W workflow chain: done = [■ Label], active = [▶ Label], pending = [ Label ], rejected/cancelled = [✗]
     * @param PodanieLog[] $logs
     */
    private function buildWorkflowChainHtml(string $status, array $steps, array $logs): string
    {
        if (empty($steps)) {
            return '';
        }

        $isRejected = $status === 'odrzucony';
        $isCancelled = $status === 'anulowany';
        $isApproved = $status === 'zatwierdzony';

        $doneStepKeys = [];
        foreach ($logs as $log) {
            if (str_starts_with($log->getTransition(), 'akceptuj_')) {
                $doneStepKeys[] = substr($log->getTransition(), 9);
            }
        }

        $nextStepLabel = $this->statusProvider->getNextStepLabel($status);

        // Styles: done=bold+thick border, active=bold+dashed, pending=thin, rejected=thin+strikethrough
        $sDone = 'padding:2px 6px;font-weight:bold;font-size:7.5pt;border:1.5px solid #333;color:#222;';
        $sActive = 'padding:2px 6px;font-weight:bold;font-size:7.5pt;border:1.5px dashed #333;color:#222;';
        $sPending = 'padding:2px 6px;font-size:7.5pt;border:1px solid #bbb;color:#aaa;';
        $sTerminal = 'padding:2px 6px;font-size:7.5pt;border:1px solid #bbb;color:#aaa;text-decoration:line-through;';

        $html = '<table style="width:100%;border-collapse:collapse;margin-bottom:6px;"><tr>';
        $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sDone . '">Złożony</span></td>';

        foreach ($steps as $step) {
            $l = htmlspecialchars($step->getLabel(), ENT_QUOTES, 'UTF-8');
            $html .= '<td style="text-align:center;padding:2px 1px;font-size:9pt;color:#999;">&rarr;</td>';

            if (in_array($step->getKey(), $doneStepKeys, true)) {
                $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sDone . '">' . $l . '</span></td>';
            } elseif ($isRejected || $isCancelled) {
                $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sTerminal . '">' . $l . '</span></td>';
            } elseif ($nextStepLabel === $step->getLabel()) {
                $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sActive . '">&#9654; ' . $l . '</span></td>';
            } else {
                $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sPending . '">' . $l . '</span></td>';
            }
        }

        $html .= '<td style="text-align:center;padding:2px 1px;font-size:9pt;color:#999;">&rarr;</td>';
        if ($isApproved) {
            $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sDone . '">Zatwierdzony</span></td>';
        } elseif ($isRejected) {
            $html .= '<td style="text-align:center;padding:2px 1px;"><span style="padding:2px 6px;font-weight:bold;font-size:7.5pt;border:1.5px solid #333;color:#222;">ODRZUCONY</span></td>';
        } elseif ($isCancelled) {
            $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sTerminal . '">Anulowany</span></td>';
        } else {
            $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sPending . '">Zatwierdzony</span></td>';
        }

        $html .= '</tr></table>';
        return $html;
    }

    /**
     * @param PodanieLog[] $logs
     */
    private function buildWorkflowApprovalsHtml(string $status, array $steps, array $logs): string
    {
        if (empty($steps)) {
            return '';
        }

        $isTerminal = in_array($status, ['odrzucony', 'anulowany'], true);

        $html = '<table style="width:100%;border-collapse:collapse;margin-top:14px;">';

        $reachedTerminal = false;

        foreach ($steps as $step) {
            $label = htmlspecialchars($step->getLabel(), ENT_QUOTES, 'UTF-8');

            // Find matching log
            $stepLog = null;
            foreach ($logs as $log) {
                if ($log->getTransition() === 'akceptuj_' . $step->getKey() || $log->getTransition() === 'odrzuc_' . $step->getKey()) {
                    $stepLog = $log;
                }
            }

            // If previous step was rejected/cancelled, skip remaining steps
            if ($reachedTerminal) {
                continue;
            }

            $html .= '<tr><td style="border:1px solid #999;padding:8px 12px;">';
            $html .= '<div style="font-size:7.5pt;font-weight:bold;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:6px;color:#333;">' . $label . '</div>';

            if ($stepLog) {
                // Filled electronically — strikethrough the opposite
                $isAccept = str_starts_with($stepLog->getTransition(), 'akceptuj_');
                if ($isAccept) {
                    $html .= '<div style="font-size:8.5pt;margin-bottom:3px;">';
                    $html .= '<strong>Wyrażam zgodę</strong>';
                    $html .= ' / <span style="text-decoration:line-through;color:#aaa;">Nie wyrażam zgody</span>';
                    $html .= '</div>';
                } else {
                    $html .= '<div style="font-size:8.5pt;margin-bottom:3px;">';
                    $html .= '<span style="text-decoration:line-through;color:#aaa;">Wyrażam zgodę</span>';
                    $html .= ' / <strong>Nie wyrażam zgody</strong>';
                    $html .= '</div>';
                    $reachedTerminal = true;
                }
                $html .= '<div style="font-size:7.5pt;color:#555;">';
                if ($stepLog->getUser()) {
                    $html .= htmlspecialchars($this->userResolver->getFullName($stepLog->getUser()), ENT_QUOTES, 'UTF-8') . ' &middot; ';
                }
                $html .= $stepLog->getCreatedAt()->format('d.m.Y H:i');
                $html .= '</div>';
                if ($stepLog->getKomentarz()) {
                    $html .= '<div style="font-size:7.5pt;margin-top:3px;color:#333;">Uzasadnienie: ' . htmlspecialchars($stepLog->getKomentarz(), ENT_QUOTES, 'UTF-8') . '</div>';
                }
            } else {
                // Not filled — both options without strikethrough, to be crossed out manually
                $html .= '<div style="font-size:8.5pt;margin-bottom:4px;">';
                $html .= 'Wyrażam zgodę(*) / Nie wyrażam zgody(*)';
                $html .= '</div>';
                $html .= '<div style="font-size:7.5pt;color:#555;margin-bottom:3px;">Uzasadnienie: <span style="border-bottom:1px dotted #888;display:inline-block;width:70%;height:12px;"></span></div>';
                $html .= '<table style="width:100%;border-collapse:collapse;margin-top:6px;"><tr>';
                $html .= '<td style="padding:0;width:50%;"><div style="border-bottom:1px dotted #888;height:18px;"></div><div style="font-size:6.5pt;color:#777;margin-top:1px;">(data)</div></td>';
                $html .= '<td style="padding:0 0 0 16px;width:50%;"><div style="border-bottom:1px dotted #888;height:18px;"></div><div style="font-size:6.5pt;color:#777;margin-top:1px;">(pieczątka i podpis)</div></td>';
                $html .= '</tr></table>';
            }

            $html .= '</td></tr>';
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * @param PodanieLog[] $logs
     */
    private function buildWorkflowHistoriaHtml(PodanieUrlopowe $podanie, array $logs): string
    {
        if (empty($logs)) {
            return '';
        }

        $html = '<table style="width:100%;border-collapse:collapse;font-size:8.5pt;">';
        $html .= '<thead><tr>';
        $html .= '<th style="background:#f9fafb;border:1px solid #e5e7eb;padding:4px 8px;text-align:left;font-size:8pt;text-transform:uppercase;color:#555;width:100px;">Data</th>';
        $html .= '<th style="background:#f9fafb;border:1px solid #e5e7eb;padding:4px 8px;text-align:left;font-size:8pt;text-transform:uppercase;color:#555;">Operacja</th>';
        $html .= '<th style="background:#f9fafb;border:1px solid #e5e7eb;padding:4px 8px;text-align:left;font-size:8pt;text-transform:uppercase;color:#555;">Osoba</th>';
        $html .= '<th style="background:#f9fafb;border:1px solid #e5e7eb;padding:4px 8px;text-align:left;font-size:8pt;text-transform:uppercase;color:#555;">Uwagi</th>';
        $html .= '</tr></thead><tbody>';

        // First row: submission
        $html .= '<tr>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">' . $podanie->getCreatedAt()->format('d.m.Y H:i') . '</td>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">Złożenie podania</td>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">' . htmlspecialchars($this->userResolver->getFullName($podanie->getUser()), ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;"></td>';
        $html .= '</tr>';

        foreach ($logs as $log) {
            $html .= '<tr>';
            $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">' . $log->getCreatedAt()->format('d.m.Y H:i') . '</td>';

            // Operation with color
            if (str_starts_with($log->getTransition(), 'akceptuj_')) {
                $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;color:#166534;">Akceptacja: ' . htmlspecialchars($this->statusProvider->getLabel($log->getToStatus()), ENT_QUOTES, 'UTF-8') . '</td>';
            } elseif (str_starts_with($log->getTransition(), 'odrzuc_')) {
                $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;color:#991b1b;">Odrzucenie</td>';
            } elseif ($log->getTransition() === 'anuluj') {
                $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;color:#6b7280;">Anulowanie</td>';
            } else {
                $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">' . htmlspecialchars($log->getTransition(), ENT_QUOTES, 'UTF-8') . '</td>';
            }

            $userName = $log->getUser() ? htmlspecialchars($this->userResolver->getFullName($log->getUser()), ENT_QUOTES, 'UTF-8') : '';
            $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">' . $userName . '</td>';
            $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">' . htmlspecialchars($log->getKomentarz() ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function buildSampleWorkflowChainHtml(): string
    {
        $steps = $this->workflowFactory->getActiveSteps();
        if (empty($steps)) {
            $steps = [
                (object) ['label' => 'Szef działu', 'key' => 'szef'],
                (object) ['label' => 'Kadry', 'key' => 'kadry'],
                (object) ['label' => 'Naczelny', 'key' => 'naczelny'],
            ];
        }

        $sDone = 'padding:2px 6px;font-weight:bold;font-size:7.5pt;border:1.5px solid #333;color:#222;';
        $sActive = 'padding:2px 6px;font-weight:bold;font-size:7.5pt;border:1.5px dashed #333;color:#222;';
        $sPending = 'padding:2px 6px;font-size:7.5pt;border:1px solid #bbb;color:#aaa;';

        $html = '<table style="width:100%;border-collapse:collapse;margin-bottom:6px;"><tr>';
        $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sDone . '">Złożony</span></td>';

        foreach ($steps as $i => $step) {
            $label = is_object($step) && method_exists($step, 'getLabel') ? $step->getLabel() : $step->label;
            $html .= '<td style="text-align:center;padding:2px 1px;font-size:9pt;color:#999;">&rarr;</td>';
            if ($i === 0) {
                $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sDone . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></td>';
            } elseif ($i === 1) {
                $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sActive . '">&#9654; ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></td>';
            } else {
                $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sPending . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></td>';
            }
        }
        $html .= '<td style="text-align:center;padding:2px 1px;font-size:9pt;color:#999;">&rarr;</td>';
        $html .= '<td style="text-align:center;padding:2px 1px;"><span style="' . $sPending . '">Zatwierdzony</span></td>';
        $html .= '</tr></table>';

        return $html;
    }

    private function buildSampleWorkflowApprovalsHtml(): string
    {
        $steps = $this->workflowFactory->getActiveSteps();
        $labels = [];
        if (!empty($steps)) {
            foreach ($steps as $step) {
                $labels[] = $step->getLabel();
            }
        } else {
            $labels = ['Szef działu', 'Kadry', 'Naczelny'];
        }

        $html = '<table style="width:100%;border-collapse:collapse;margin-top:14px;">';
        foreach ($labels as $i => $label) {
            $html .= '<tr><td style="border:1px solid #999;padding:8px 12px;">';
            $html .= '<div style="font-size:7.5pt;font-weight:bold;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:6px;color:#333;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';

            if ($i === 0) {
                $html .= '<div style="font-size:8.5pt;margin-bottom:3px;">';
                $html .= '<strong>Wyrażam zgodę</strong>';
                $html .= ' / <span style="text-decoration:line-through;color:#aaa;">Nie wyrażam zgody</span>';
                $html .= '</div>';
                $html .= '<div style="font-size:7.5pt;color:#555;">Adam Nowak &middot; ' . date('d.m.Y H:i') . '</div>';
            } else {
                $html .= '<div style="font-size:8.5pt;margin-bottom:4px;">';
                $html .= 'Wyrażam zgodę(*) / Nie wyrażam zgody(*)';
                $html .= '</div>';
                $html .= '<div style="font-size:7.5pt;color:#555;margin-bottom:3px;">Uzasadnienie: <span style="border-bottom:1px dotted #888;display:inline-block;width:70%;height:12px;"></span></div>';
                $html .= '<table style="width:100%;border-collapse:collapse;margin-top:6px;"><tr>';
                $html .= '<td style="padding:0;width:50%;"><div style="border-bottom:1px dotted #888;height:18px;"></div><div style="font-size:6.5pt;color:#777;margin-top:1px;">(data)</div></td>';
                $html .= '<td style="padding:0 0 0 16px;width:50%;"><div style="border-bottom:1px dotted #888;height:18px;"></div><div style="font-size:6.5pt;color:#777;margin-top:1px;">(pieczątka i podpis)</div></td>';
                $html .= '</tr></table>';
            }

            $html .= '</td></tr>';
        }
        $html .= '</table>';

        return $html;
    }

    private function buildSampleWorkflowHistoriaHtml(): string
    {
        $html = '<table style="width:100%;border-collapse:collapse;font-size:8.5pt;">';
        $html .= '<thead><tr>';
        $html .= '<th style="background:#f9fafb;border:1px solid #e5e7eb;padding:4px 8px;text-align:left;font-size:8pt;text-transform:uppercase;color:#555;width:100px;">Data</th>';
        $html .= '<th style="background:#f9fafb;border:1px solid #e5e7eb;padding:4px 8px;text-align:left;font-size:8pt;text-transform:uppercase;color:#555;">Operacja</th>';
        $html .= '<th style="background:#f9fafb;border:1px solid #e5e7eb;padding:4px 8px;text-align:left;font-size:8pt;text-transform:uppercase;color:#555;">Osoba</th>';
        $html .= '<th style="background:#f9fafb;border:1px solid #e5e7eb;padding:4px 8px;text-align:left;font-size:8pt;text-transform:uppercase;color:#555;">Uwagi</th>';
        $html .= '</tr></thead><tbody>';

        $html .= '<tr>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">' . date('d.m.Y H:i') . '</td>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">Złożenie podania</td>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">Jan Kowalski</td>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;"></td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">' . date('d.m.Y H:i') . '</td>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;color:#166534;">Akceptacja: Szef OK</td>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;">Adam Nowak</td>';
        $html .= '<td style="border:1px solid #e5e7eb;padding:4px 8px;"></td>';
        $html .= '</tr>';

        $html .= '</tbody></table>';
        return $html;
    }

    private function buildTypPodaniaSkreslenie(PodanieUrlopowe $podanie): string
    {
        $typPodania = $podanie->getTypPodania();
        $typy = ['urlopu', 'czasu wolnego od pracy'];

        if (!$typPodania) {
            return 'urlopu(*), <span style="text-decoration:line-through;">czasu wolnego od pracy(*)</span>';
        }

        $parts = [];
        foreach ($typy as $t) {
            if ($t === $typPodania->getNazwa()) {
                $parts[] = '<strong>' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</strong>';
            } else {
                $parts[] = '<span style="text-decoration:line-through;">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</span>';
            }
        }

        return implode(', ', $parts);
    }

    private function buildUrlopCzasSkreslenie(PodanieUrlopowe $podanie): string
    {
        $typPodania = $podanie->getTypPodania();
        $nazwa = $typPodania?->getNazwa();

        $urlop = 'urlopu(*)';
        $czasWolny = 'czasu wolnego od pracy(*)';

        if ($nazwa === 'czasu wolnego od pracy') {
            return '<span style="text-decoration:line-through;">' . $urlop . '</span>, ' . $czasWolny;
        }

        return $urlop . ', <span style="text-decoration:line-through;">' . $czasWolny . '</span>';
    }
}
