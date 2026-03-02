<?php

namespace Planer\PlanerBundle\Controller;

use Planer\PlanerBundle\Entity\Departament;
use Planer\PlanerBundle\Repository\DzienWolnyFirmyRepository;
use Planer\PlanerBundle\Repository\PodanieUrlopoweRepository;
use Planer\PlanerBundle\Service\ModulChecker;
use Planer\PlanerBundle\Service\PlanerUserResolver;
use Planer\PlanerBundle\Service\PodanieStatusProvider;
use Planer\PlanerBundle\Service\PolishHolidayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/planer')]
#[IsGranted('ROLE_USER')]
class UrlopyFirmaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlanerUserResolver $resolver,
    ) {
    }

    #[Route('/urlopy-firma/{rok}/{miesiac}', name: 'planer_urlopy_firma', methods: ['GET'],
        defaults: ['rok' => null, 'miesiac' => null],
        requirements: ['rok' => '\d{4}', 'miesiac' => '1[0-2]|[1-9]'])]
    public function index(
        ?int $rok,
        ?int $miesiac,
        PodanieUrlopoweRepository $podanieRepo,
        PodanieStatusProvider $statusProvider,
        PolishHolidayService $holidayService,
        DzienWolnyFirmyRepository $dzienWolnyRepo,
        ModulChecker $modulChecker,
    ): Response {
        if (!$modulChecker->hasAccess('urlopy_firma')) {
            throw $this->createAccessDeniedException('Brak dostępu do modułu Urlopy firma.');
        }

        $rok = $rok ?? (int) date('Y');
        $miesiac = $miesiac ?? (int) date('n');

        $departamenty = $this->em->getRepository(Departament::class)
            ->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']);

        $activeStatuses = $statusProvider->getActiveStatuses();
        $podania = $podanieRepo->findActiveForMonth($rok, $miesiac, $activeStatuses);

        $monthStart = new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac));
        $dniWMiesiacu = (int) $monthStart->format('t');

        // Day metadata (weekend/holiday)
        $firmoweDniWolne = $dzienWolnyRepo->findMapForYear($rok);
        $swieta = $holidayService->getHolidaysForMonth($rok, $miesiac, $firmoweDniWolne);

        $dni = [];
        for ($d = 1; $d <= $dniWMiesiacu; $d++) {
            $date = new \DateTime(sprintf('%04d-%02d-%02d', $rok, $miesiac, $d));
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = (int) $date->format('N');
            $swietoNazwa = $swieta[$dateStr] ?? null;

            $dni[$d] = [
                'numer' => $d,
                'dzienTygodnia' => $this->polishDayShort($dayOfWeek),
                'weekend' => $dayOfWeek >= 6,
                'swieto' => $swietoNazwa !== null,
                'swietoNazwa' => $swietoNazwa,
            ];
        }

        // Build grid[deptId][day] = count with dedup
        $grid = [];
        $totals = array_fill(1, $dniWMiesiacu, 0);
        $seen = [];

        $monthStr = sprintf('%04d-%02d', $rok, $miesiac);

        foreach ($podania as $p) {
            $deptId = $p->getDepartament()->getId();
            $userId = $p->getUser()->getId();

            // Clamp dates to current month
            $podanieOd = $p->getDataOd();
            $podanieDo = $p->getDataDo();
            $clampedOd = ($podanieOd->format('Y-m') < $monthStr) ? 1 : (int) $podanieOd->format('j');
            $clampedDo = ($podanieDo->format('Y-m') > $monthStr) ? $dniWMiesiacu : (int) $podanieDo->format('j');

            for ($d = $clampedOd; $d <= $clampedDo; $d++) {
                $key = $deptId . '-' . $d . '-' . $userId;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                if (!isset($grid[$deptId][$d])) {
                    $grid[$deptId][$d] = 0;
                }
                $grid[$deptId][$d]++;
                $totals[$d]++;
            }
        }

        // Prev/next month
        $prevDate = (new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac)))->modify('-1 month');
        $nextDate = (new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac)))->modify('+1 month');

        return $this->render('@Planer/urlopy_firma.html.twig', [
            'rok' => $rok,
            'miesiac' => $miesiac,
            'miesiacNazwa' => $this->polishMonthNameFull($miesiac),
            'dni' => $dni,
            'dniWMiesiacu' => $dniWMiesiacu,
            'departamenty' => $departamenty,
            'grid' => $grid,
            'totals' => $totals,
            'prevRok' => (int) $prevDate->format('Y'),
            'prevMiesiac' => (int) $prevDate->format('n'),
            'nextRok' => (int) $nextDate->format('Y'),
            'nextMiesiac' => (int) $nextDate->format('n'),
        ]);
    }

    #[Route('/urlopy-firma/{rok}/{miesiac}/detail', name: 'planer_urlopy_firma_detail', methods: ['GET'],
        requirements: ['rok' => '\d{4}', 'miesiac' => '1[0-2]|[1-9]'])]
    public function detail(
        int $rok,
        int $miesiac,
        Request $request,
        PodanieUrlopoweRepository $podanieRepo,
        PodanieStatusProvider $statusProvider,
        ModulChecker $modulChecker,
    ): JsonResponse {
        if (!$modulChecker->hasAccess('urlopy_firma')) {
            return new JsonResponse(['error' => 'Brak dostępu do modułu Urlopy firma'], 403);
        }

        $dzien = $request->query->getInt('dzien');
        $deptId = $request->query->getInt('dept');

        if ($dzien < 1 || $dzien > 31) {
            return new JsonResponse(['error' => 'Invalid day'], 400);
        }

        $activeStatuses = $statusProvider->getActiveStatuses();
        $podania = $podanieRepo->findActiveForMonth($rok, $miesiac, $activeStatuses);

        $monthStr = sprintf('%04d-%02d', $rok, $miesiac);
        $dniWMiesiacu = (int) (new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac)))->format('t');

        $seen = [];
        $people = [];

        foreach ($podania as $p) {
            if ($deptId > 0 && $p->getDepartament()->getId() !== $deptId) {
                continue;
            }

            $podanieOd = $p->getDataOd();
            $podanieDo = $p->getDataDo();
            $clampedOd = ($podanieOd->format('Y-m') < $monthStr) ? 1 : (int) $podanieOd->format('j');
            $clampedDo = ($podanieDo->format('Y-m') > $monthStr) ? $dniWMiesiacu : (int) $podanieDo->format('j');

            if ($dzien < $clampedOd || $dzien > $clampedDo) {
                continue;
            }

            $userId = $p->getUser()->getId();
            $dedupKey = $userId . '-' . ($deptId > 0 ? $deptId : $p->getDepartament()->getId());
            if (isset($seen[$dedupKey])) {
                continue;
            }
            $seen[$dedupKey] = true;

            $people[] = [
                'name' => $this->resolver->getFullName($p->getUser()),
                'departament' => $p->getDepartament()->getNazwa(),
                'dataOd' => $p->getDataOd()->format('d.m.Y'),
                'dataDo' => $p->getDataDo()->format('d.m.Y'),
                'rodzajUrlopu' => $p->getRodzajUrlopu()?->getNazwa() ?? '-',
                'status' => $statusProvider->getLabel($p->getStatus()),
            ];
        }

        usort($people, fn($a, $b) => $a['name'] <=> $b['name']);

        return new JsonResponse(['people' => $people]);
    }

    private function polishDayShort(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            1 => 'Pn', 2 => 'Wt', 3 => 'Śr', 4 => 'Cz',
            5 => 'Pt', 6 => 'So', 7 => 'Nd',
        };
    }

    private function polishMonthNameFull(int $month): string
    {
        return match ($month) {
            1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec',
            4 => 'Kwiecień', 5 => 'Maj', 6 => 'Czerwiec',
            7 => 'Lipiec', 8 => 'Sierpień', 9 => 'Wrzesień',
            10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
        };
    }
}
