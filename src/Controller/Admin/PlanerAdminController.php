<?php

namespace Planer\PlanerBundle\Controller\Admin;

use Dompdf\Dompdf;
use Dompdf\Options;
use Planer\PlanerBundle\Entity\Departament;
use Planer\PlanerBundle\Entity\DzienWolnyFirmy;
use Planer\PlanerBundle\Entity\GrafikWpis;
use Planer\PlanerBundle\Entity\PodanieUrlopowe;
use Planer\PlanerBundle\Entity\RodzajUrlopu;
use Planer\PlanerBundle\Entity\SzablonPodania;
use Planer\PlanerBundle\Entity\TypPodania;
use Planer\PlanerBundle\Entity\TypZmiany;
use Planer\PlanerBundle\Entity\UserDepartament;
use Planer\PlanerBundle\Model\PlanerUserInterface;
use Planer\PlanerBundle\Repository\GrafikWpisRepository;
use Planer\PlanerBundle\Repository\PlanerUstawieniaRepository;
use Planer\PlanerBundle\Repository\UserDepartamentRepository;
use Planer\PlanerBundle\Service\PdfImportService;
use Planer\PlanerBundle\Service\PlaceholderReplacerService;
use Planer\PlanerBundle\Service\PolishHolidayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/planer/admin')]
#[IsGranted('ROLE_ADMIN')]
class PlanerAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    // ──────────────────────────────────────
    //  DASHBOARD
    // ──────────────────────────────────────

    #[Route('/', name: 'planer_admin_dashboard', methods: ['GET'])]
    public function dashboard(
        PolishHolidayService $holidayService,
        UserDepartamentRepository $udRepo,
    ): Response {
        $rok = (int) date('Y');

        $departamentyCount = $this->em->getRepository(Departament::class)->count([]);
        $typyZmianCount = $this->em->getRepository(TypZmiany::class)->count([]);
        $pracownicyCount = $udRepo->countDistinctUsers();
        $dniWolneCount = $this->em->getRepository(DzienWolnyFirmy::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.data BETWEEN :start AND :end')
            ->setParameter('start', new \DateTime("$rok-01-01"))
            ->setParameter('end', new \DateTime("$rok-12-31"))
            ->getQuery()
            ->getSingleScalarResult();

        $swietaPanstwowe = $holidayService->getPublicHolidays($rok);
        $today = date('Y-m-d');
        $najblizszeSwieto = null;
        foreach ($swietaPanstwowe as $data => $nazwa) {
            if ($data >= $today) {
                $najblizszeSwieto = ['data' => $data, 'nazwa' => $nazwa];
                break;
            }
        }

        return $this->render('@Planer/admin/dashboard.html.twig', [
            'active' => 'dashboard',
            'departamentyCount' => $departamentyCount,
            'typyZmianCount' => $typyZmianCount,
            'pracownicyCount' => $pracownicyCount,
            'dniWolneCount' => $dniWolneCount,
            'najblizszeSwieto' => $najblizszeSwieto,
            'rok' => $rok,
        ]);
    }

    // ──────────────────────────────────────
    //  PRACOWNICY (przypisania do departamentów)
    // ──────────────────────────────────────

    #[Route('/pracownicy', name: 'planer_admin_pracownicy', methods: ['GET'])]
    public function pracownicy(UserDepartamentRepository $udRepo): Response
    {
        $departamenty = $this->em->getRepository(Departament::class)
            ->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']);

        $users = $udRepo->findAllPlanerUsers();

        $assignments = $udRepo->findAllGroupedByUser();

        return $this->render('@Planer/admin/pracownicy.html.twig', [
            'active' => 'pracownicy',
            'departamenty' => $departamenty,
            'users' => $users,
            'assignments' => $assignments,
        ]);
    }

    #[Route('/pracownicy/sync', name: 'planer_admin_pracownicy_sync', methods: ['POST'])]
    public function pracownicySync(Request $request, UserDepartamentRepository $udRepo): Response
    {
        $departamenty = $this->em->getRepository(Departament::class)->findAll();
        $departamentyById = [];
        foreach ($departamenty as $d) {
            $departamentyById[$d->getId()] = $d;
        }

        $users = $udRepo->findAllPlanerUsers();
        $usersById = [];
        foreach ($users as $u) {
            $usersById[$u->getId()] = $u;
        }

        $existing = $udRepo->findAll();
        $existingMap = [];
        foreach ($existing as $ud) {
            $existingMap[$ud->getUser()->getId() . '_' . $ud->getDepartament()->getId()] = $ud;
        }

        $postedAssign = $request->request->all('assign') ?: [];
        $postedSzef = $request->request->all('szef') ?: [];
        $postedGlowny = $request->request->all('glowny') ?: [];
        $postedAdres = $request->request->all('adres') ?: [];
        $postedUkryty = $request->request->all('ukryty') ?: [];

        // Save address for each user
        foreach ($postedAdres as $userId => $adres) {
            if (isset($usersById[$userId])) {
                $usersById[$userId]->setAdres(trim($adres) ?: null);
            }
        }

        $seen = [];

        foreach ($postedAssign as $userId => $deptIds) {
            if (!isset($usersById[$userId])) {
                continue;
            }
            $user = $usersById[$userId];

            foreach ($deptIds as $deptId) {
                if (!isset($departamentyById[$deptId])) {
                    continue;
                }
                $dept = $departamentyById[$deptId];
                $key = $userId . '_' . $deptId;
                $seen[$key] = true;

                if (isset($existingMap[$key])) {
                    $ud = $existingMap[$key];
                } else {
                    $ud = new UserDepartament($user, $dept);
                    $this->em->persist($ud);
                }

                $isSzef = isset($postedSzef[$userId]) && in_array((string) $deptId, (array) $postedSzef[$userId], true);
                $ud->setCzySzef($isSzef);

                $isGlowny = isset($postedGlowny[$userId]) && (string) $postedGlowny[$userId] === (string) $deptId;
                $ud->setCzyGlowny($isGlowny);

                $isUkryty = isset($postedUkryty[$userId]) && in_array((string) $deptId, (array) $postedUkryty[$userId], true);
                $ud->setCzyUkryty($isUkryty);
            }
        }

        foreach ($existingMap as $key => $ud) {
            if (!isset($seen[$key])) {
                $this->em->remove($ud);
            }
        }

        $this->em->flush();

        $this->addFlash('success', 'Przypisania pracowników zostały zapisane.');
        return $this->redirectToRoute('planer_admin_pracownicy');
    }

    // ──────────────────────────────────────
    //  URLOPY
    // ──────────────────────────────────────

    #[Route('/urlopy/{departamentId}/{rok}', name: 'planer_admin_urlopy', methods: ['GET'],
        defaults: ['departamentId' => null, 'rok' => null],
        requirements: ['departamentId' => '\d+', 'rok' => '\d{4}'])]
    public function urlopy(
        ?int $departamentId,
        ?int $rok,
        UserDepartamentRepository $udRepo,
        GrafikWpisRepository $gwRepo,
    ): Response {
        $rok = $rok ?? (int) date('Y');

        $departamenty = $this->em->getRepository(Departament::class)
            ->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']);

        if (!$departamenty) {
            return $this->render('@Planer/admin/urlopy.html.twig', [
                'active' => 'urlopy',
                'departamenty' => [],
                'wybranyDept' => null,
                'rok' => $rok,
                'users' => [],
                'urlopyPerUser' => [],
                'miesiace' => [],
            ]);
        }

        $wybranyDept = null;
        if ($departamentId) {
            foreach ($departamenty as $d) {
                if ($d->getId() === $departamentId) {
                    $wybranyDept = $d;
                    break;
                }
            }
        }
        if (!$wybranyDept) {
            $wybranyDept = $departamenty[0];
        }

        $userDepartamenty = $udRepo->findUsersForDepartament($wybranyDept);
        $users = [];
        foreach ($userDepartamenty as $ud) {
            $users[$ud->getUser()->getId()] = $ud->getUser();
        }

        $urlopyPerUser = $gwRepo->findVacationForDepartamentAndYear($wybranyDept, $rok);

        // Build date ranges per user: [userId => ['Sty: 3-5, 14-15', 'Mar: 1-3', ...]]
        $miesiaceNazwy = [1 => 'Sty', 2 => 'Lut', 3 => 'Mar', 4 => 'Kwi', 5 => 'Maj', 6 => 'Cze',
            7 => 'Lip', 8 => 'Sie', 9 => 'Wrz', 10 => 'Paź', 11 => 'Lis', 12 => 'Gru'];
        $zakresyPerUser = [];
        foreach ($urlopyPerUser as $userId => $months) {
            $parts = [];
            foreach ($months as $month => $days) {
                $ranges = $this->daysToRanges($days);
                $parts[] = $miesiaceNazwy[$month] . ': ' . $ranges;
            }
            $zakresyPerUser[$userId] = $parts;
        }

        $miesiace = array_values($miesiaceNazwy);

        return $this->render('@Planer/admin/urlopy.html.twig', [
            'active' => 'urlopy',
            'departamenty' => $departamenty,
            'wybranyDept' => $wybranyDept,
            'rok' => $rok,
            'users' => $users,
            'urlopyPerUser' => $urlopyPerUser,
            'zakresyPerUser' => $zakresyPerUser,
            'miesiace' => $miesiace,
        ]);
    }

    // ──────────────────────────────────────
    //  RAPORT URLOPOWY PDF
    // ──────────────────────────────────────

    #[Route('/urlopy/{departamentId}/{rok}/raport-pdf', name: 'planer_admin_urlopy_raport_pdf', methods: ['GET'],
        requirements: ['departamentId' => '\d+', 'rok' => '\d{4}'])]
    public function urlopRaportPdf(
        int $departamentId,
        int $rok,
        UserDepartamentRepository $udRepo,
        GrafikWpisRepository $gwRepo,
        Environment $twig,
        PlanerUstawieniaRepository $ustawieniaRepo,
    ): Response {
        $firmaNazwa = $ustawieniaRepo->getSettings()->getFirmaNazwa();
        $dept = $this->em->getRepository(Departament::class)->find($departamentId);
        if (!$dept) {
            throw $this->createNotFoundException('Departament nie istnieje.');
        }

        $userDepartamenty = $udRepo->findUsersForDepartament($dept);
        $users = [];
        foreach ($userDepartamenty as $ud) {
            if ($ud->isCzyGlowny()) {
                $users[$ud->getUser()->getId()] = $ud->getUser();
            }
        }

        $urlopyPerUser = $gwRepo->findVacationForDepartamentAndYear($dept, $rok);

        $miesiaceNazwy = [1 => 'Sty', 2 => 'Lut', 3 => 'Mar', 4 => 'Kwi', 5 => 'Maj', 6 => 'Cze',
            7 => 'Lip', 8 => 'Sie', 9 => 'Wrz', 10 => 'Paź', 11 => 'Lis', 12 => 'Gru'];
        $zakresyPerUser = [];
        foreach ($urlopyPerUser as $userId => $months) {
            if (!isset($users[$userId])) {
                continue;
            }
            $parts = [];
            foreach ($months as $month => $days) {
                $ranges = $this->daysToRanges($days);
                $parts[] = $miesiaceNazwy[$month] . ': ' . $ranges;
            }
            $zakresyPerUser[$userId] = $parts;
        }

        $raportData = [];
        $sumyMiesiac = array_fill_keys(array_keys($miesiaceNazwy), 0);
        $sumaWykorzystane = 0;

        foreach ($users as $userId => $user) {
            $userUrlopy = $urlopyPerUser[$userId] ?? [];
            $razem = 0;
            $perMonth = [];
            foreach ($miesiaceNazwy as $m => $mNazwa) {
                $count = count($userUrlopy[$m] ?? []);
                $razem += $count;
                $perMonth[$m] = $count;
                $sumyMiesiac[$m] += $count;
            }
            $sumaWykorzystane += $razem;
            $raportData[] = [
                'user' => $user,
                'perMonth' => $perMonth,
                'razem' => $razem,
                'limit' => $user->getIloscDniUrlopuWRoku(),
                'pozostalo' => $user->getIloscDniUrlopuWRoku() - $razem,
                'zakresy' => $zakresyPerUser[$userId] ?? [],
            ];
        }

        $html = $twig->render('@Planer/admin/urlopy_raport_pdf.html.twig', [
            'dept' => $dept,
            'rok' => $rok,
            'firmaNazwa' => $firmaNazwa,
            'miesiace' => $miesiaceNazwy,
            'raportData' => $raportData,
            'sumyMiesiac' => $sumyMiesiac,
            'sumaWykorzystane' => $sumaWykorzystane,
            'dataGenerowania' => new \DateTime(),
        ]);

        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf('raport_urlopy_%s_%d.pdf', $dept->getSkrot(), $rok);

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
        ));

        return $response;
    }

    // ──────────────────────────────────────
    //  PODANIE URLOPOWE
    // ──────────────────────────────────────

    private const SZABLONY = ['urlop', 'praca_zdalna', 'nadgodziny', 'opieka'];
    private const SZABLON_LABELS = [
        'urlop' => 'Podanie o urlop',
        'praca_zdalna' => 'Wniosek o pracę zdalną',
        'nadgodziny' => 'Wniosek o odbiór nadgodzin',
        'opieka' => 'Opieka nad dzieckiem',
    ];

    #[Route('/urlopy/podanie/{userId}/{rok}', name: 'planer_admin_podanie_form', methods: ['GET'],
        requirements: ['userId' => '\d+', 'rok' => '\d{4}'])]
    public function podanieForm(
        int $userId,
        int $rok,
        Request $request,
        GrafikWpisRepository $gwRepo,
        UserDepartamentRepository $udRepo,
    ): Response {
        $userClass = $this->getParameter('planer.user_class');
        $user = $this->em->getRepository($userClass)->find($userId);
        if (!$user) {
            throw $this->createNotFoundException('Użytkownik nie istnieje.');
        }

        $dept = $user->getGlownyDepartament();
        if (!$dept) {
            $depts = $user->getDepartamentList();
            $dept = $depts[0] ?? null;
        }

        // Resolve typZmiany and szablon
        $typZmianyId = $request->query->getInt('typZmianyId');
        $typZmiany = $typZmianyId ? $this->em->getRepository(TypZmiany::class)->find($typZmianyId) : null;

        $szablonEntity = $typZmiany?->getSzablon();
        $polaFormularza = null;
        $szablon = 'urlop';
        $szablonLabel = 'Podanie o urlop';

        if ($szablonEntity) {
            $polaFormularza = $szablonEntity->getPolaFormularza();
            $szablonLabel = $szablonEntity->getNazwa();
        } else {
            $szablon = $typZmiany?->getSzablonPodania() ?? 'urlop';
            if (!in_array($szablon, self::SZABLONY, true)) {
                $szablon = 'urlop';
            }
            $szablonLabel = self::SZABLON_LABELS[$szablon] ?? $szablon;
        }

        $dataOd = $request->query->getString('dataOd');
        $dataDo = $request->query->getString('dataDo');

        if (!$dataOd || !$dataDo) {
            $dataOd = $dataOd ?: date('Y-m-d');
            $dataDo = $dataDo ?: date('Y-m-d');
        }

        $templateVars = [
            'active' => 'podania',
            'user' => $user,
            'rok' => $rok,
            'dept' => $dept,
            'dataOd' => $dataOd,
            'dataDo' => $dataDo,
            'szablon' => $szablon,
            'szablonLabel' => $szablonLabel,
            'typZmianyId' => $typZmianyId,
            'polaFormularza' => $polaFormularza,
        ];

        $needsTypPodania = $polaFormularza ? in_array('typ_podania', $polaFormularza, true) : ($szablon === 'urlop');
        $needsRodzajUrlopu = $polaFormularza ? in_array('rodzaj_urlopu', $polaFormularza, true) : ($szablon === 'urlop');
        $needsZastepca = $polaFormularza ? in_array('zastepca', $polaFormularza, true) : ($szablon === 'urlop');

        if ($needsTypPodania) {
            $templateVars['typyPodania'] = $this->em->getRepository(TypPodania::class)->findBy([], ['kolejnosc' => 'ASC']);
        }
        if ($needsRodzajUrlopu) {
            $templateVars['rodzajeUrlopu'] = $this->em->getRepository(RodzajUrlopu::class)->findBy([], ['kolejnosc' => 'ASC']);
        }
        if ($needsZastepca) {
            $templateVars['wspolpracownicy'] = [];
            if ($dept) {
                foreach ($udRepo->findUsersForDepartament($dept, false) as $ud) {
                    if ($ud->getUser()->getId() !== $user->getId()) {
                        $templateVars['wspolpracownicy'][] = $ud->getUser();
                    }
                }
            }
        }

        return $this->render('@Planer/admin/podanie_form.html.twig', $templateVars);
    }

    #[Route('/urlopy/podanie/{userId}/{rok}', name: 'planer_admin_podanie_generate', methods: ['POST'],
        requirements: ['userId' => '\d+', 'rok' => '\d{4}'])]
    public function podanieGenerate(
        int $userId,
        int $rok,
        Request $request,
    ): Response {
        $userClass = $this->getParameter('planer.user_class');
        $user = $this->em->getRepository($userClass)->find($userId);
        if (!$user) {
            throw $this->createNotFoundException('Użytkownik nie istnieje.');
        }

        $dept = $user->getGlownyDepartament();
        if (!$dept) {
            $depts = $user->getDepartamentList();
            $dept = $depts[0] ?? null;
        }
        if (!$dept) {
            throw $this->createNotFoundException('Użytkownik nie ma przypisanego departamentu.');
        }

        $typZmianyId = $request->request->getInt('typ_zmiany_id');
        $typZmiany = $typZmianyId ? $this->em->getRepository(TypZmiany::class)->find($typZmianyId) : null;
        $szablonEntity = $typZmiany?->getSzablon();

        $dataOd = new \DateTime($request->request->getString('data_od'));
        $dataDo = new \DateTime($request->request->getString('data_do'));
        $podpis = trim($request->request->getString('podpis')) ?: null;

        $podanie = new PodanieUrlopowe();
        $podanie->setUser($user);
        $podanie->setDepartament($dept);
        $podanie->setDataOd($dataOd);
        $podanie->setDataDo($dataDo);
        $podanie->setPodpis($podpis);
        $podanie->setTypZmiany($typZmiany);

        if ($szablonEntity) {
            $polaFormularza = $szablonEntity->getPolaFormularza();
            if (in_array('zastepca', $polaFormularza, true)) {
                $podanie->setZastepca($request->request->getString('zastepca'));
            }
            if (in_array('telefon', $polaFormularza, true)) {
                $podanie->setTelefon($request->request->getString('telefon'));
            }
            if (in_array('typ_podania', $polaFormularza, true)) {
                $typPodaniaId = $request->request->getInt('typ_podania_id');
                $podanie->setTypPodania($typPodaniaId ? $this->em->find(TypPodania::class, $typPodaniaId) : null);
            }
            if (in_array('rodzaj_urlopu', $polaFormularza, true)) {
                $rodzajUrlopuId = $request->request->getInt('rodzaj_urlopu_id');
                $podanie->setRodzajUrlopu($rodzajUrlopuId ? $this->em->find(RodzajUrlopu::class, $rodzajUrlopuId) : null);
            }
        } else {
            $szablon = $typZmiany?->getSzablonPodania() ?? 'urlop';
            if ($szablon === 'urlop') {
                $podanie->setZastepca($request->request->getString('zastepca'));
                $podanie->setTelefon($request->request->getString('telefon'));

                $typPodaniaId = $request->request->getInt('typ_podania_id');
                $rodzajUrlopuId = $request->request->getInt('rodzaj_urlopu_id');
                $podanie->setTypPodania($typPodaniaId ? $this->em->find(TypPodania::class, $typPodaniaId) : null);
                $podanie->setRodzajUrlopu($rodzajUrlopuId ? $this->em->find(RodzajUrlopu::class, $rodzajUrlopuId) : null);
            }
        }

        $this->em->persist($podanie);
        $this->em->flush();

        $this->addFlash('success', 'Podanie zostało zapisane. Kliknij "Pobierz PDF" aby pobrać dokument.');

        return $this->redirectToRoute('planer_admin_podania');
    }

    #[Route('/podania/{id}/pdf', name: 'planer_admin_podanie_pdf', methods: ['GET'],
        requirements: ['id' => '\d+'])]
    public function podaniePdf(
        int $id,
        Environment $twig,
        PlanerUstawieniaRepository $ustawieniaRepo,
        PlaceholderReplacerService $replacerService,
    ): Response {
        $settings = $ustawieniaRepo->getSettings();
        $podanie = $this->em->getRepository(PodanieUrlopowe::class)->find($id);
        if (!$podanie) {
            throw $this->createNotFoundException('Podanie nie istnieje.');
        }

        $szablonEntity = $podanie->getTypZmiany()?->getSzablon();

        if ($szablonEntity) {
            $html = $replacerService->replace($szablonEntity->getTrescHtml(), $podanie, $settings);
        } else {
            // FALLBACK: old Twig-based rendering
            $szablon = $podanie->getTypZmiany()?->getSzablonPodania() ?? 'urlop';
            if (!in_array($szablon, self::SZABLONY, true)) {
                $szablon = 'urlop';
            }

            $html = $twig->render('@Planer/podanie/_pdf_' . $szablon . '.html.twig', [
                'user' => $podanie->getUser(),
                'dataOd' => $podanie->getDataOd(),
                'dataDo' => $podanie->getDataDo(),
                'zastepca' => $podanie->getZastepca(),
                'telefon' => $podanie->getTelefon(),
                'firmaNazwa' => $settings->getFirmaNazwa(),
                'firmaAdres' => $settings->getFirmaAdres(),
                'dataZlozenia' => $podanie->getCreatedAt(),
                'typPodania' => $podanie->getTypPodania(),
                'rodzajUrlopu' => $podanie->getRodzajUrlopu(),
                'podpis' => $podanie->getPodpis(),
            ]);
        }

        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filenameFallback = sprintf('podanie_%d.pdf', $podanie->getId());

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filenameFallback,
        ));

        return $response;
    }

    #[Route('/podania', name: 'planer_admin_podania', methods: ['GET'])]
    public function podaniaIndex(): Response
    {
        $podania = $this->em->getRepository(PodanieUrlopowe::class)
            ->findBy([], ['createdAt' => 'DESC']);

        return $this->render('@Planer/admin/podania.html.twig', [
            'active' => 'podania',
            'podania' => $podania,
        ]);
    }

    #[Route('/podania/{id}/delete', name: 'planer_admin_podanie_delete', methods: ['POST'],
        requirements: ['id' => '\d+'])]
    public function podanieDelete(int $id): Response
    {
        $podanie = $this->em->getRepository(PodanieUrlopowe::class)->find($id);
        if ($podanie) {
            $this->em->remove($podanie);
            $this->em->flush();
            $this->addFlash('success', 'Podanie zostało usunięte.');
        }

        return $this->redirectToRoute('planer_admin_podania');
    }

    // ──────────────────────────────────────
    //  DEPARTAMENTY
    // ──────────────────────────────────────

    #[Route('/departamenty', name: 'departament_index', methods: ['GET'])]
    public function departamentIndex(): Response
    {
        $departamenty = $this->em->getRepository(Departament::class)
            ->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']);

        return $this->render('@Planer/admin/departament/index.html.twig', [
            'active' => 'departamenty',
            'departamenty' => $departamenty,
        ]);
    }

    #[Route('/departamenty/new', name: 'departament_new', methods: ['GET', 'POST'])]
    public function departamentNew(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $departament = new Departament();
            $departament->setNazwa($request->request->getString('nazwa'));
            $departament->setSkrot($request->request->getString('skrot'));
            $departament->setKolejnosc($request->request->getInt('kolejnosc'));

            $this->em->persist($departament);
            $this->em->flush();

            $this->addFlash('success', 'Departament został utworzony.');
            return $this->redirectToRoute('departament_index');
        }

        return $this->render('@Planer/admin/departament/new.html.twig', [
            'active' => 'departamenty',
        ]);
    }

    #[Route('/departamenty/{id}/edit', name: 'departament_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function departamentEdit(Departament $departament, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $departament->setNazwa($request->request->getString('nazwa'));
            $departament->setSkrot($request->request->getString('skrot'));
            $departament->setKolejnosc($request->request->getInt('kolejnosc'));

            $this->em->flush();

            $this->addFlash('success', 'Departament został zaktualizowany.');
            return $this->redirectToRoute('departament_index');
        }

        return $this->render('@Planer/admin/departament/edit.html.twig', [
            'active' => 'departamenty',
            'departament' => $departament,
        ]);
    }

    #[Route('/departamenty/{id}/delete', name: 'departament_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function departamentDelete(Departament $departament): Response
    {
        try {
            $this->em->remove($departament);
            $this->em->flush();
            $this->addFlash('success', 'Departament został usunięty.');
        } catch (\Exception) {
            $this->addFlash('error', 'Nie można usunąć departamentu — posiada powiązane elementy.');
        }

        return $this->redirectToRoute('departament_index');
    }

    // ──────────────────────────────────────
    //  TYPY ZMIAN
    // ──────────────────────────────────────

    #[Route('/typy-zmian', name: 'typ_zmiany_index', methods: ['GET'])]
    public function typZmianyIndex(): Response
    {
        $typy = $this->em->getRepository(TypZmiany::class)
            ->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']);

        return $this->render('@Planer/admin/typ_zmiany/index.html.twig', [
            'active' => 'typy_zmian',
            'typy' => $typy,
        ]);
    }

    #[Route('/typy-zmian/new', name: 'typ_zmiany_new', methods: ['GET', 'POST'])]
    public function typZmianyNew(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $typ = new TypZmiany();
            $typ->setNazwa($request->request->getString('nazwa'));
            $typ->setSkrot($request->request->getString('skrot'));
            $typ->setKolor($request->request->getString('kolor'));
            $typ->setGodzinyOd($request->request->getString('godziny_od') ?: null);
            $typ->setGodzinyDo($request->request->getString('godziny_do') ?: null);
            $typ->setAktywny($request->request->getBoolean('aktywny'));
            $typ->setKolejnosc($request->request->getInt('kolejnosc'));
            $typ->setSkrotKlawiaturowy($request->request->getString('skrot_klawiaturowy') ?: null);
            $typ->setTylkoGlowny($request->request->getBoolean('tylko_glowny'));
            $this->syncDepartamenty($typ, $request->request->all('departamenty'));

            $szablonIdRaw = $request->request->get('szablon_id', '');
            $szablonId = $szablonIdRaw !== '' ? (int) $szablonIdRaw : null;
            $szablon = $szablonId ? $this->em->getRepository(SzablonPodania::class)->find($szablonId) : null;
            $typ->setSzablon($szablon);
            $typ->setSzablonPodania($szablon ? 'custom' : null);

            $this->em->persist($typ);
            $this->em->flush();

            $this->addFlash('success', 'Typ zmiany został utworzony.');
            return $this->redirectToRoute('typ_zmiany_index');
        }

        return $this->render('@Planer/admin/typ_zmiany/new.html.twig', [
            'active' => 'typy_zmian',
            'existingShortcuts' => $this->getExistingShortcuts(),
            'departamenty' => $this->em->getRepository(Departament::class)->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']),
            'szablony' => $this->em->getRepository(SzablonPodania::class)->findBy(['aktywny' => true], ['nazwa' => 'ASC']),
        ]);
    }

    #[Route('/typy-zmian/{id}/edit', name: 'typ_zmiany_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function typZmianyEdit(TypZmiany $typZmiany, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $typZmiany->setNazwa($request->request->getString('nazwa'));
            $typZmiany->setSkrot($request->request->getString('skrot'));
            $typZmiany->setKolor($request->request->getString('kolor'));
            $typZmiany->setGodzinyOd($request->request->getString('godziny_od') ?: null);
            $typZmiany->setGodzinyDo($request->request->getString('godziny_do') ?: null);
            $typZmiany->setAktywny($request->request->getBoolean('aktywny'));
            $typZmiany->setKolejnosc($request->request->getInt('kolejnosc'));
            $typZmiany->setSkrotKlawiaturowy($request->request->getString('skrot_klawiaturowy') ?: null);
            $typZmiany->setTylkoGlowny($request->request->getBoolean('tylko_glowny'));
            $this->syncDepartamenty($typZmiany, $request->request->all('departamenty'));

            $szablonIdRaw = $request->request->get('szablon_id', '');
            $szablonId = $szablonIdRaw !== '' ? (int) $szablonIdRaw : null;
            $szablon = $szablonId ? $this->em->getRepository(SzablonPodania::class)->find($szablonId) : null;
            $typZmiany->setSzablon($szablon);
            $typZmiany->setSzablonPodania($szablon ? 'custom' : null);

            $this->em->flush();

            $this->addFlash('success', 'Typ zmiany został zaktualizowany.');
            return $this->redirectToRoute('typ_zmiany_index');
        }

        return $this->render('@Planer/admin/typ_zmiany/edit.html.twig', [
            'active' => 'typy_zmian',
            'typ' => $typZmiany,
            'existingShortcuts' => $this->getExistingShortcuts($typZmiany->getId()),
            'departamenty' => $this->em->getRepository(Departament::class)->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']),
            'szablony' => $this->em->getRepository(SzablonPodania::class)->findBy(['aktywny' => true], ['nazwa' => 'ASC']),
        ]);
    }

    #[Route('/typy-zmian/toggle', name: 'typ_zmiany_toggle', methods: ['POST'])]
    public function typZmianyToggle(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = $data['id'] ?? null;
        $aktywny = $data['aktywny'] ?? null;

        if (!$id || $aktywny === null) {
            return new JsonResponse(['error' => 'Brak danych'], 400);
        }

        $typ = $this->em->getRepository(TypZmiany::class)->find($id);
        if (!$typ) {
            return new JsonResponse(['error' => 'Nie znaleziono'], 404);
        }

        $typ->setAktywny((bool) $aktywny);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/typy-zmian/reorder', name: 'typ_zmiany_reorder', methods: ['POST'])]
    public function typZmianyReorder(Request $request): JsonResponse
    {
        $ids = json_decode($request->getContent(), true)['ids'] ?? [];
        if (!$ids) {
            return new JsonResponse(['error' => 'Brak danych'], 400);
        }

        $repo = $this->em->getRepository(TypZmiany::class);
        foreach ($ids as $pos => $id) {
            $typ = $repo->find($id);
            if ($typ) {
                $typ->setKolejnosc($pos);
            }
        }
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/typy-zmian/{id}/delete', name: 'typ_zmiany_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function typZmianyDelete(TypZmiany $typZmiany): Response
    {
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(gw.id)')
            ->from(GrafikWpis::class, 'gw')
            ->where('gw.typZmiany = :typ')
            ->setParameter('typ', $typZmiany)
            ->getQuery()
            ->getSingleScalarResult();

        if ($count > 0) {
            $this->addFlash('error', sprintf(
                'Nie można usunąć — typ "%s" jest używany w %d wpisach grafiku. Możesz go wyłączyć zamiast usuwać.',
                $typZmiany->getNazwa(),
                $count
            ));
            return $this->redirectToRoute('typ_zmiany_index');
        }

        $this->em->remove($typZmiany);
        $this->em->flush();
        $this->addFlash('success', 'Typ zmiany został usunięty.');

        return $this->redirectToRoute('typ_zmiany_index');
    }

    // ──────────────────────────────────────
    //  DNI WOLNE
    // ──────────────────────────────────────

    #[Route('/dni-wolne', name: 'dzien_wolny_index', methods: ['GET'])]
    public function dzienWolnyIndex(PolishHolidayService $holidayService): Response
    {
        $rok = (int) date('Y');
        $dniWolne = $this->em->getRepository(DzienWolnyFirmy::class)->findBy([], ['data' => 'ASC']);
        $swietaPanstwowe = $holidayService->getPublicHolidays($rok);

        return $this->render('@Planer/admin/dzien_wolny/index.html.twig', [
            'active' => 'dni_wolne',
            'dniWolne' => $dniWolne,
            'swietaPanstwowe' => $swietaPanstwowe,
            'rok' => $rok,
        ]);
    }

    #[Route('/dni-wolne/new', name: 'dzien_wolny_new', methods: ['GET', 'POST'])]
    public function dzienWolnyNew(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $dzien = new DzienWolnyFirmy();
            $dzien->setNazwa($request->request->getString('nazwa'));

            $dataStr = $request->request->getString('data');
            if ($dataStr) {
                $dzien->setData(new \DateTime($dataStr));
            }

            $this->em->persist($dzien);
            $this->em->flush();

            $this->addFlash('success', 'Dzień wolny został dodany.');
            return $this->redirectToRoute('dzien_wolny_index');
        }

        return $this->render('@Planer/admin/dzien_wolny/new.html.twig', [
            'active' => 'dni_wolne',
        ]);
    }

    #[Route('/dni-wolne/{id}/edit', name: 'dzien_wolny_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function dzienWolnyEdit(DzienWolnyFirmy $dzienWolny, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $dzienWolny->setNazwa($request->request->getString('nazwa'));

            $dataStr = $request->request->getString('data');
            if ($dataStr) {
                $dzienWolny->setData(new \DateTime($dataStr));
            }

            $this->em->flush();

            $this->addFlash('success', 'Dzień wolny został zaktualizowany.');
            return $this->redirectToRoute('dzien_wolny_index');
        }

        return $this->render('@Planer/admin/dzien_wolny/edit.html.twig', [
            'active' => 'dni_wolne',
            'dzienWolny' => $dzienWolny,
        ]);
    }

    #[Route('/dni-wolne/{id}/delete', name: 'dzien_wolny_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function dzienWolnyDelete(DzienWolnyFirmy $dzienWolny): Response
    {
        $this->em->remove($dzienWolny);
        $this->em->flush();

        $this->addFlash('success', 'Dzień wolny został usunięty.');
        return $this->redirectToRoute('dzien_wolny_index');
    }

    // ──────────────────────────────────────
    //  USTAWIENIA
    // ──────────────────────────────────────

    #[Route('/ustawienia', name: 'planer_admin_ustawienia', methods: ['GET'])]
    public function ustawienia(PlanerUstawieniaRepository $repo): Response
    {
        $settings = $repo->getSettings();
        $typyZmian = $this->em->getRepository(TypZmiany::class)
            ->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']);

        return $this->render('@Planer/admin/ustawienia.html.twig', [
            'active' => 'ustawienia',
            'settings' => $settings,
            'typyZmian' => $typyZmian,
        ]);
    }

    #[Route('/ustawienia', name: 'planer_admin_ustawienia_save', methods: ['POST'])]
    public function ustawieniaSave(Request $request, PlanerUstawieniaRepository $repo): Response
    {
        $settings = $repo->getSettings();

        $settings->setFirmaNazwa($request->request->getString('firma_nazwa'));
        $settings->setFirmaAdres($request->request->getString('firma_adres'));

        $zmianaId = $request->request->getInt('auto_plan_zmiana_id');
        $wolneId = $request->request->getInt('auto_plan_wolne_id');

        $settings->setAutoPlanZmiana(
            $zmianaId ? $this->em->getRepository(TypZmiany::class)->find($zmianaId) : null
        );
        $settings->setAutoPlanWolne(
            $wolneId ? $this->em->getRepository(TypZmiany::class)->find($wolneId) : null
        );

        $this->em->flush();

        $this->addFlash('success', 'Ustawienia zostały zapisane.');

        return $this->redirectToRoute('planer_admin_ustawienia');
    }

    private function syncDepartamenty(TypZmiany $typ, array $deptIds): void
    {
        // Wyczyść obecne
        foreach ($typ->getDepartamenty()->toArray() as $dept) {
            $typ->removeDepartament($dept);
        }
        // Dodaj zaznaczone
        foreach ($deptIds as $id) {
            $dept = $this->em->getRepository(Departament::class)->find((int) $id);
            if ($dept) {
                $typ->addDepartament($dept);
            }
        }
    }

    private function getExistingShortcuts(?int $excludeId = null): array
    {
        $typy = $this->em->getRepository(TypZmiany::class)->findAll();
        $shortcuts = [];
        foreach ($typy as $typ) {
            if ($typ->getSkrotKlawiaturowy() && $typ->getId() !== $excludeId) {
                $shortcuts[] = ['combo' => $typ->getSkrotKlawiaturowy(), 'nazwa' => $typ->getNazwa()];
            }
        }
        return $shortcuts;
    }

    /**
     * Groups sorted day numbers into "from-to" range strings.
     * e.g. [1,2,3,7,8,14] => "1-3, 7-8, 14"
     *
     * @param list<int> $days sorted
     */
    private function daysToRanges(array $days): string
    {
        if (!$days) {
            return '';
        }

        $ranges = [];
        $start = $days[0];
        $prev = $days[0];

        for ($i = 1, $count = count($days); $i < $count; $i++) {
            if ($days[$i] === $prev + 1) {
                $prev = $days[$i];
            } else {
                $ranges[] = $start === $prev ? (string) $start : $start . '-' . $prev;
                $start = $days[$i];
                $prev = $days[$i];
            }
        }
        $ranges[] = $start === $prev ? (string) $start : $start . '-' . $prev;

        return implode(', ', $ranges);
    }

    // ──────────────────────────────────────
    //  SZABLONY PODAŃ
    // ──────────────────────────────────────

    #[Route('/szablony', name: 'planer_admin_szablony', methods: ['GET'])]
    public function szablonIndex(): Response
    {
        $szablony = $this->em->getRepository(SzablonPodania::class)
            ->findBy([], ['id' => 'ASC']);

        return $this->render('@Planer/admin/szablon/index.html.twig', [
            'active' => 'szablony',
            'szablony' => $szablony,
        ]);
    }

    #[Route('/szablony/new', name: 'planer_admin_szablon_new', methods: ['GET', 'POST'])]
    public function szablonNew(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $szablon = new SzablonPodania();
            $szablon->setNazwa($request->request->getString('nazwa'));
            $szablon->setTrescHtml($request->request->getString('tresc_html'));
            $szablon->setPolaFormularza($request->request->all('pola_formularza') ?: []);
            $szablon->setAktywny($request->request->getBoolean('aktywny'));

            $this->em->persist($szablon);
            $this->em->flush();

            $this->addFlash('success', 'Szablon został utworzony.');
            return $this->redirectToRoute('planer_admin_szablony');
        }

        return $this->render('@Planer/admin/szablon/new.html.twig', [
            'active' => 'szablony',
            'placeholders' => PlaceholderReplacerService::getPlaceholderReference(),
        ]);
    }

    #[Route('/szablony/{id}/edit', name: 'planer_admin_szablon_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function szablonEdit(int $id, Request $request): Response
    {
        $szablon = $this->em->getRepository(SzablonPodania::class)->find($id);
        if (!$szablon) {
            throw $this->createNotFoundException('Szablon nie istnieje.');
        }

        if ($request->isMethod('POST')) {
            $szablon->setNazwa($request->request->getString('nazwa'));
            $szablon->setTrescHtml($request->request->getString('tresc_html'));
            $szablon->setPolaFormularza($request->request->all('pola_formularza') ?: []);
            $szablon->setAktywny($request->request->getBoolean('aktywny'));
            $szablon->setUpdatedAt(new \DateTime());

            $this->em->flush();

            $this->addFlash('success', 'Szablon został zaktualizowany.');
            return $this->redirectToRoute('planer_admin_szablony');
        }

        return $this->render('@Planer/admin/szablon/edit.html.twig', [
            'active' => 'szablony',
            'szablon' => $szablon,
            'placeholders' => PlaceholderReplacerService::getPlaceholderReference(),
        ]);
    }

    #[Route('/szablony/{id}/delete', name: 'planer_admin_szablon_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function szablonDelete(int $id): Response
    {
        $szablon = $this->em->getRepository(SzablonPodania::class)->find($id);
        if (!$szablon) {
            throw $this->createNotFoundException('Szablon nie istnieje.');
        }

        // Check if used by any TypZmiany
        $usageCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(TypZmiany::class, 't')
            ->where('t.szablon = :szablon')
            ->setParameter('szablon', $szablon)
            ->getQuery()
            ->getSingleScalarResult();

        if ($usageCount > 0) {
            $this->addFlash('error', sprintf(
                'Nie można usunąć — szablon "%s" jest przypisany do %d typów zmian.',
                $szablon->getNazwa(),
                $usageCount
            ));
            return $this->redirectToRoute('planer_admin_szablony');
        }

        $this->em->remove($szablon);
        $this->em->flush();
        $this->addFlash('success', 'Szablon został usunięty.');

        return $this->redirectToRoute('planer_admin_szablony');
    }

    #[Route('/szablony/{id}/preview-pdf', name: 'planer_admin_szablon_preview_pdf', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function szablonPreviewPdf(
        int $id,
        Request $request,
        PlanerUstawieniaRepository $ustawieniaRepo,
        PlaceholderReplacerService $replacerService,
    ): Response {
        $szablon = $this->em->getRepository(SzablonPodania::class)->find($id);
        if (!$szablon) {
            throw $this->createNotFoundException('Szablon nie istnieje.');
        }

        $htmlContent = $request->request->getString('tresc_html') ?: $szablon->getTrescHtml();
        $settings = $ustawieniaRepo->getSettings();
        $html = $replacerService->replaceWithSampleData($htmlContent, $settings);

        return $this->generatePdfResponse($html, 'podglad_szablon.pdf');
    }

    #[Route('/szablony/preview-pdf', name: 'planer_admin_szablon_preview_pdf_new', methods: ['POST'])]
    public function szablonPreviewPdfNew(
        Request $request,
        PlanerUstawieniaRepository $ustawieniaRepo,
        PlaceholderReplacerService $replacerService,
    ): Response {
        $htmlContent = $request->request->getString('tresc_html');
        if (!$htmlContent) {
            return new Response('Brak treści HTML.', 400);
        }

        $settings = $ustawieniaRepo->getSettings();
        $html = $replacerService->replaceWithSampleData($htmlContent, $settings);

        return $this->generatePdfResponse($html, 'podglad_szablon.pdf');
    }

    #[Route('/szablony/import-pdf', name: 'planer_admin_szablon_import_pdf', methods: ['POST'])]
    public function szablonImportPdf(Request $request, PdfImportService $pdfImportService): JsonResponse
    {
        $file = $request->files->get('pdf_file');

        if (!$file) {
            return new JsonResponse(['error' => 'Nie przesłano pliku.'], 422);
        }

        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());
        $allowedMimes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ];

        if (!in_array($mime, $allowedMimes) && !in_array($ext, ['pdf', 'docx'])) {
            return new JsonResponse(['error' => 'Plik musi być w formacie PDF lub DOCX.'], 422);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse(['error' => 'Plik jest za duży (maks. 5 MB).'], 422);
        }

        try {
            $content = file_get_contents($file->getPathname());
            $isDocx = $ext === 'docx' || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

            $html = $isDocx
                ? $pdfImportService->convertDocxToHtml($content)
                : $pdfImportService->convertPdfToHtml($content);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Nie udało się odczytać pliku: ' . $e->getMessage()], 422);
        }

        return new JsonResponse([
            'html' => $html,
            'filename' => $file->getClientOriginalName(),
        ]);
    }

    private function generatePdfResponse(string $html, string $filename): Response
    {
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $filename,
        ));

        return $response;
    }

    // ──────────────────────────────────────
    //  SŁOWNIKI (Typ podania, Rodzaj urlopu)
    // ──────────────────────────────────────

    #[Route('/slowniki', name: 'planer_admin_slowniki', methods: ['GET'])]
    public function slowniki(): Response
    {
        $typyPodania = $this->em->getRepository(TypPodania::class)->findBy([], ['kolejnosc' => 'ASC']);
        $rodzajeUrlopu = $this->em->getRepository(RodzajUrlopu::class)->findBy([], ['kolejnosc' => 'ASC']);

        return $this->render('@Planer/admin/slowniki/index.html.twig', [
            'active' => 'slowniki',
            'typyPodania' => $typyPodania,
            'rodzajeUrlopu' => $rodzajeUrlopu,
        ]);
    }

    #[Route('/slowniki/typ-podania/new', name: 'planer_admin_typ_podania_new', methods: ['GET', 'POST'])]
    public function typPodaniaNew(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $typ = new TypPodania();
            $typ->setNazwa($request->request->getString('nazwa'));
            $typ->setKolejnosc($request->request->getInt('kolejnosc'));

            $this->em->persist($typ);
            $this->em->flush();

            $this->addFlash('success', 'Typ podania został utworzony.');
            return $this->redirectToRoute('planer_admin_slowniki');
        }

        return $this->render('@Planer/admin/slowniki/typ_podania_form.html.twig', [
            'active' => 'slowniki',
            'typ' => null,
        ]);
    }

    #[Route('/slowniki/typ-podania/{id}/edit', name: 'planer_admin_typ_podania_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function typPodaniaEdit(int $id, Request $request): Response
    {
        $typ = $this->em->find(TypPodania::class, $id);
        if (!$typ) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $typ->setNazwa($request->request->getString('nazwa'));
            $typ->setKolejnosc($request->request->getInt('kolejnosc'));

            $this->em->flush();

            $this->addFlash('success', 'Typ podania został zaktualizowany.');
            return $this->redirectToRoute('planer_admin_slowniki');
        }

        return $this->render('@Planer/admin/slowniki/typ_podania_form.html.twig', [
            'active' => 'slowniki',
            'typ' => $typ,
        ]);
    }

    #[Route('/slowniki/typ-podania/{id}/delete', name: 'planer_admin_typ_podania_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function typPodaniaDelete(int $id): Response
    {
        $typ = $this->em->find(TypPodania::class, $id);
        if ($typ) {
            try {
                $this->em->remove($typ);
                $this->em->flush();
                $this->addFlash('success', 'Typ podania został usunięty.');
            } catch (\Exception) {
                $this->addFlash('error', 'Nie można usunąć — typ jest używany w podaniach.');
            }
        }

        return $this->redirectToRoute('planer_admin_slowniki');
    }

    #[Route('/slowniki/rodzaj-urlopu/new', name: 'planer_admin_rodzaj_urlopu_new', methods: ['GET', 'POST'])]
    public function rodzajUrlopuNew(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $rodzaj = new RodzajUrlopu();
            $rodzaj->setNazwa($request->request->getString('nazwa'));
            $rodzaj->setKolejnosc($request->request->getInt('kolejnosc'));

            $this->em->persist($rodzaj);
            $this->em->flush();

            $this->addFlash('success', 'Rodzaj urlopu został utworzony.');
            return $this->redirectToRoute('planer_admin_slowniki');
        }

        return $this->render('@Planer/admin/slowniki/rodzaj_urlopu_form.html.twig', [
            'active' => 'slowniki',
            'rodzaj' => null,
        ]);
    }

    #[Route('/slowniki/rodzaj-urlopu/{id}/edit', name: 'planer_admin_rodzaj_urlopu_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function rodzajUrlopuEdit(int $id, Request $request): Response
    {
        $rodzaj = $this->em->find(RodzajUrlopu::class, $id);
        if (!$rodzaj) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $rodzaj->setNazwa($request->request->getString('nazwa'));
            $rodzaj->setKolejnosc($request->request->getInt('kolejnosc'));

            $this->em->flush();

            $this->addFlash('success', 'Rodzaj urlopu został zaktualizowany.');
            return $this->redirectToRoute('planer_admin_slowniki');
        }

        return $this->render('@Planer/admin/slowniki/rodzaj_urlopu_form.html.twig', [
            'active' => 'slowniki',
            'rodzaj' => $rodzaj,
        ]);
    }

    #[Route('/slowniki/rodzaj-urlopu/{id}/delete', name: 'planer_admin_rodzaj_urlopu_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function rodzajUrlopuDelete(int $id): Response
    {
        $rodzaj = $this->em->find(RodzajUrlopu::class, $id);
        if ($rodzaj) {
            try {
                $this->em->remove($rodzaj);
                $this->em->flush();
                $this->addFlash('success', 'Rodzaj urlopu został usunięty.');
            } catch (\Exception) {
                $this->addFlash('error', 'Nie można usunąć — rodzaj jest używany w podaniach.');
            }
        }

        return $this->redirectToRoute('planer_admin_slowniki');
    }
}
