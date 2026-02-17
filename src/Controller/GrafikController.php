<?php

namespace Planer\PlanerBundle\Controller;

use Planer\PlanerBundle\Entity\Departament;
use Planer\PlanerBundle\Entity\GrafikWpis;
use Planer\PlanerBundle\Entity\TypZmiany;
use Planer\PlanerBundle\Model\PlanerUserInterface;
use Planer\PlanerBundle\Repository\DzienWolnyFirmyRepository;
use Planer\PlanerBundle\Repository\GrafikWpisRepository;
use Planer\PlanerBundle\Repository\PlanerUstawieniaRepository;
use Planer\PlanerBundle\Repository\PodanieUrlopoweRepository;
use Planer\PlanerBundle\Repository\UserDepartamentRepository;
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
class GrafikController extends AbstractController
{
    #[Route('/{departamentId}/{rok}/{miesiac}', name: 'grafik_index', defaults: ['departamentId' => null, 'rok' => null, 'miesiac' => null], requirements: ['departamentId' => '\d+', 'rok' => '\d{4}', 'miesiac' => '\d{1,2}'])]
    public function index(
        ?int $departamentId,
        ?int $rok,
        ?int $miesiac,
        EntityManagerInterface $em,
        UserDepartamentRepository $userDepartamentRepo,
        GrafikWpisRepository $grafikWpisRepo,
        PolishHolidayService $holidayService,
        DzienWolnyFirmyRepository $dzienWolnyRepo,
        PodanieUrlopoweRepository $podanieRepo,
    ): Response {
        /** @var PlanerUserInterface $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $rok = $rok ?? (int) date('Y');
        $miesiac = $miesiac ?? (int) date('n');

        if ($miesiac < 1) { $miesiac = 1; }
        if ($miesiac > 12) { $miesiac = 12; }

        $allDepartamenty = $em->getRepository(Departament::class)->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']);

        if ($isAdmin) {
            $dostepneDepartamenty = $allDepartamenty;
        } else {
            $dostepneDepartamenty = $this->getUserDepartaments($currentUser, $userDepartamentRepo);
        }

        if (empty($dostepneDepartamenty)) {
            return $this->render('@Planer/grafik/index.html.twig', [
                'brakDepartamentu' => true,
            ]);
        }

        $departament = null;
        if ($departamentId) {
            $departament = $em->getRepository(Departament::class)->find($departamentId);
        }

        if ($departament && !$isAdmin) {
            $hasAccess = false;
            foreach ($dostepneDepartamenty as $d) {
                if ($d->getId() === $departament->getId()) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) {
                $departament = null;
            }
        }

        if (!$departament) {
            $glowny = $this->getUserGlownyDepartament($currentUser, $userDepartamentRepo);
            if ($glowny && ($isAdmin || in_array($glowny, $dostepneDepartamenty, false))) {
                $departament = $glowny;
            } else {
                $departament = $dostepneDepartamenty[0];
            }
        }

        $canEdit = $isAdmin || $this->isUserSzefDepartamentu($currentUser, $departament, $userDepartamentRepo);

        $userDepartamenty = $userDepartamentRepo->findUsersForDepartament($departament, false);

        $users = [];
        $glownyUserIds = [];
        foreach ($userDepartamenty as $ud) {
            $users[] = [
                'user' => $ud->getUser(),
                'czySzef' => $ud->isCzySzef(),
                'czyGlowny' => $ud->isCzyGlowny(),
            ];
            if ($ud->isCzyGlowny()) {
                $glownyUserIds[] = $ud->getUser()->getId();
            }
        }

        $grid = $grafikWpisRepo->findForDepartamentAndMonth($departament, $rok, $miesiac);

        $typyZmianAll = $em->getRepository(TypZmiany::class)->findBy([], ['kolejnosc' => 'ASC']);
        $typyZmian = array_values(array_filter($typyZmianAll, fn(TypZmiany $t) => $t->isAktywny() && $t->isAvailableForDepartament($departament)));

        // Build a map: typZmianyId → szablonPodania (for JS)
        $typyZmianSzablony = [];
        foreach ($typyZmian as $tz) {
            if ($tz->getSzablonPodania()) {
                $typyZmianSzablony[$tz->getId()] = $tz->getSzablonPodania();
            }
        }

        $dniWMiesiacu = (int) (new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac)))->format('t');

        // Święta i firmowe dni wolne
        $firmowe = $dzienWolnyRepo->findMapForYear($rok);
        $swieta = $holidayService->getHolidaysForMonth($rok, $miesiac, $firmowe);

        $dni = [];
        for ($d = 1; $d <= $dniWMiesiacu; $d++) {
            $date = new \DateTime(sprintf('%04d-%02d-%02d', $rok, $miesiac, $d));
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = (int) $date->format('N');
            $swietoNazwa = $swieta[$dateStr] ?? null;

            $dni[$d] = [
                'numer' => $d,
                'dzienTygodnia' => $this->polishDayName($dayOfWeek),
                'weekend' => $dayOfWeek >= 6,
                'swieto' => $swietoNazwa !== null,
                'swietoNazwa' => $swietoNazwa,
            ];
        }

        $prevDate = (new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac)))->modify('-1 month');
        $nextDate = (new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac)))->modify('+1 month');

        // Liczba dni wolnych w miesiącu (weekendy + święta)
        $liczbaDniWolnych = 0;
        foreach ($dni as $d) {
            if ($d['weekend'] || $d['swieto']) {
                $liczbaDniWolnych++;
            }
        }

        // Liczba wpisów "W" (Wolne) per user
        $dniWolnePerUser = [];
        foreach ($grid as $userId => $days) {
            $count = 0;
            foreach ($days as $wpis) {
                if ($wpis->getTypZmiany()->getSkrot() === 'W') {
                    $count++;
                }
            }
            $dniWolnePerUser[$userId] = $count;
        }

        // Podania urlopowe pokrywające ten miesiąc: userId-day → podanieId
        $podaniaRaw = $podanieRepo->findForDepartamentAndMonth($departament, $rok, $miesiac);
        $podaniaMap = []; // "userId-day" => podanieId
        foreach ($podaniaRaw as $pod) {
            $uid = $pod->getUser()->getId();
            $pid = $pod->getId();
            $curDay = clone $pod->getDataOd();
            $endDay = $pod->getDataDo();
            while ($curDay <= $endDay) {
                if ((int) $curDay->format('Y') === $rok && (int) $curDay->format('n') === $miesiac) {
                    $podaniaMap[$uid . '-' . (int) $curDay->format('j')] = $pid;
                }
                $curDay->modify('+1 day');
            }
        }

        return $this->render('@Planer/grafik/index.html.twig', [
            'brakDepartamentu' => false,
            'departament' => $departament,
            'dostepneDepartamenty' => $dostepneDepartamenty,
            'rok' => $rok,
            'miesiac' => $miesiac,
            'miesiacNazwa' => $this->polishMonthName($miesiac),
            'users' => $users,
            'grid' => $grid,
            'typyZmian' => $typyZmian,
            'dni' => $dni,
            'dniWMiesiacu' => $dniWMiesiacu,
            'canEdit' => $canEdit,
            'prevRok' => (int) $prevDate->format('Y'),
            'prevMiesiac' => (int) $prevDate->format('n'),
            'nextRok' => (int) $nextDate->format('Y'),
            'nextMiesiac' => (int) $nextDate->format('n'),
            'glownyUserIds' => $glownyUserIds,
            'liczbaDniWolnych' => $liczbaDniWolnych,
            'dniWolnePerUser' => $dniWolnePerUser,
            'podaniaMap' => $podaniaMap,
            'currentUserId' => $currentUser->getId(),
        ]);
    }

    #[Route('/api/wpis', name: 'grafik_upsert_wpis', methods: ['POST'])]
    public function upsertWpis(
        Request $request,
        EntityManagerInterface $em,
        GrafikWpisRepository $grafikWpisRepo,
        UserDepartamentRepository $userDepartamentRepo,
    ): JsonResponse {
        /** @var PlanerUserInterface $currentUser */
        $currentUser = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $userId = $data['userId'] ?? null;
        $departamentId = $data['departamentId'] ?? null;
        $dataStr = $data['data'] ?? null;
        $typZmianyId = $data['typZmianyId'] ?? null;

        if (!$userId || !$departamentId || !$dataStr || !$typZmianyId) {
            return new JsonResponse(['error' => 'Missing fields'], 400);
        }

        $departament = $em->getRepository(Departament::class)->find($departamentId);
        if (!$departament) {
            return new JsonResponse(['error' => 'Departament not found'], 404);
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isUserSzefDepartamentu($currentUser, $departament, $userDepartamentRepo)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $user = $em->find($this->getParameter('planer.user_class') ?? PlanerUserInterface::class, $userId);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $typZmiany = $em->getRepository(TypZmiany::class)->find($typZmianyId);
        if (!$typZmiany) {
            return new JsonResponse(['error' => 'TypZmiany not found'], 404);
        }

        if (!$typZmiany->isAvailableForDepartament($departament)) {
            return new JsonResponse(['error' => sprintf('Typ "%s" nie jest dostępny w tym departamencie.', $typZmiany->getNazwa())], 422);
        }

        if ($typZmiany->isTylkoGlowny() && !$this->isGlownyDepartamentForUser($user, $departament, $userDepartamentRepo)) {
            return new JsonResponse(['error' => sprintf('Typ "%s" można przypisać tylko w głównym departamencie pracownika.', $typZmiany->getNazwa())], 422);
        }

        $date = new \DateTime($dataStr);

        $wpis = $grafikWpisRepo->findOneByUserDataDepartament($user, $date, $departament);
        if (!$wpis) {
            $wpis = new GrafikWpis();
            $wpis->setUser($user);
            $wpis->setDepartament($departament);
            $wpis->setData($date);
            $wpis->setCreatedBy($currentUser);
            $em->persist($wpis);
        }
        $wpis->setTypZmiany($typZmiany);

        $em->flush();

        return new JsonResponse([
            'success' => true,
            'skrot' => $typZmiany->getSkrot(),
            'kolor' => $typZmiany->getKolor(),
        ]);
    }

    #[Route('/api/wpis/batch', name: 'grafik_batch_wpis', methods: ['POST'])]
    public function batchWpis(
        Request $request,
        EntityManagerInterface $em,
        GrafikWpisRepository $grafikWpisRepo,
        UserDepartamentRepository $userDepartamentRepo,
    ): JsonResponse {
        /** @var PlanerUserInterface $currentUser */
        $currentUser = $this->getUser();

        $payload = json_decode($request->getContent(), true);
        if (!$payload || !isset($payload['wpisy']) || !is_array($payload['wpisy'])) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $departamentId = $payload['departamentId'] ?? null;
        $typZmianyId = $payload['typZmianyId'] ?? null;

        if (!$departamentId) {
            return new JsonResponse(['error' => 'Missing departamentId'], 400);
        }

        $departament = $em->getRepository(Departament::class)->find($departamentId);
        if (!$departament) {
            return new JsonResponse(['error' => 'Departament not found'], 404);
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isUserSzefDepartamentu($currentUser, $departament, $userDepartamentRepo)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $typZmiany = null;
        if ($typZmianyId) {
            $typZmiany = $em->getRepository(TypZmiany::class)->find($typZmianyId);
            if (!$typZmiany) {
                return new JsonResponse(['error' => 'TypZmiany not found'], 404);
            }
            if (!$typZmiany->isAvailableForDepartament($departament)) {
                return new JsonResponse(['error' => sprintf('Typ "%s" nie jest dostępny w tym departamencie.', $typZmiany->getNazwa())], 422);
            }
        }

        $results = [];

        foreach ($payload['wpisy'] as $item) {
            $userId = $item['userId'] ?? null;
            $dataStr = $item['data'] ?? null;
            if (!$userId || !$dataStr) {
                continue;
            }

            $user = $em->find($this->getParameter('planer.user_class') ?? PlanerUserInterface::class, $userId);
            if (!$user) {
                continue;
            }

            $date = new \DateTime($dataStr);

            if ($typZmiany) {
                if ($typZmiany->isTylkoGlowny() && !$this->isGlownyDepartamentForUser($user, $departament, $userDepartamentRepo)) {
                    continue;
                }

                $wpis = $grafikWpisRepo->findOneByUserDataDepartament($user, $date, $departament);
                if (!$wpis) {
                    $wpis = new GrafikWpis();
                    $wpis->setUser($user);
                    $wpis->setDepartament($departament);
                    $wpis->setData($date);
                    $wpis->setCreatedBy($currentUser);
                    $em->persist($wpis);
                }
                $wpis->setTypZmiany($typZmiany);
                $results[] = ['userId' => $userId, 'data' => $dataStr, 'skrot' => $typZmiany->getSkrot(), 'kolor' => $typZmiany->getKolor()];
            } else {
                $wpis = $grafikWpisRepo->findOneByUserDataDepartament($user, $date, $departament);
                if ($wpis) {
                    $em->remove($wpis);
                }
                $results[] = ['userId' => $userId, 'data' => $dataStr, 'skrot' => null, 'kolor' => null];
            }
        }

        $em->flush();

        return new JsonResponse(['success' => true, 'results' => $results]);
    }

    #[Route('/api/wpis', name: 'grafik_delete_wpis', methods: ['DELETE'])]
    public function deleteWpis(
        Request $request,
        EntityManagerInterface $em,
        GrafikWpisRepository $grafikWpisRepo,
        UserDepartamentRepository $userDepartamentRepo,
    ): JsonResponse {
        /** @var PlanerUserInterface $currentUser */
        $currentUser = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $userId = $data['userId'] ?? null;
        $departamentId = $data['departamentId'] ?? null;
        $dataStr = $data['data'] ?? null;

        if (!$userId || !$departamentId || !$dataStr) {
            return new JsonResponse(['error' => 'Missing fields'], 400);
        }

        $departament = $em->getRepository(Departament::class)->find($departamentId);
        if (!$departament) {
            return new JsonResponse(['error' => 'Departament not found'], 404);
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isUserSzefDepartamentu($currentUser, $departament, $userDepartamentRepo)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $user = $em->find($this->getParameter('planer.user_class') ?? PlanerUserInterface::class, $userId);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $date = new \DateTime($dataStr);
        $wpis = $grafikWpisRepo->findOneByUserDataDepartament($user, $date, $departament);

        if ($wpis) {
            $em->remove($wpis);
            $em->flush();
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/auto-plan', name: 'grafik_auto_plan', methods: ['POST'])]
    public function autoPlan(
        Request $request,
        EntityManagerInterface $em,
        GrafikWpisRepository $grafikWpisRepo,
        UserDepartamentRepository $userDepartamentRepo,
        PlanerUstawieniaRepository $ustawieniaRepo,
        PolishHolidayService $holidayService,
        DzienWolnyFirmyRepository $dzienWolnyRepo,
    ): JsonResponse {
        /** @var PlanerUserInterface $currentUser */
        $currentUser = $this->getUser();

        $payload = json_decode($request->getContent(), true);
        if (!$payload) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $departamentId = $payload['departamentId'] ?? null;
        $rok = $payload['rok'] ?? null;
        $miesiac = $payload['miesiac'] ?? null;

        if (!$departamentId || !$rok || !$miesiac) {
            return new JsonResponse(['error' => 'Missing fields'], 400);
        }

        $departament = $em->getRepository(Departament::class)->find($departamentId);
        if (!$departament) {
            return new JsonResponse(['error' => 'Departament not found'], 404);
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isUserSzefDepartamentu($currentUser, $departament, $userDepartamentRepo)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $settings = $ustawieniaRepo->getSettings();
        $typZmiana = $settings->getAutoPlanZmiana();
        $typWolne = $settings->getAutoPlanWolne();

        if (!$typZmiana && !$typWolne) {
            return new JsonResponse(['error' => 'Brak skonfigurowanych typów zmian w ustawieniach. Przejdź do Admin → Ustawienia.'], 422);
        }

        $userDepartamenty = $userDepartamentRepo->findUsersForDepartament($departament, false);
        $users = [];
        foreach ($userDepartamenty as $ud) {
            $users[] = $ud->getUser();
        }

        $firmowe = $dzienWolnyRepo->findMapForYear($rok);
        $swieta = $holidayService->getHolidaysForMonth($rok, $miesiac, $firmowe);

        $dniWMiesiacu = (int) (new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac)))->format('t');

        $count = 0;
        foreach ($users as $user) {
            for ($d = 1; $d <= $dniWMiesiacu; $d++) {
                $date = new \DateTime(sprintf('%04d-%02d-%02d', $rok, $miesiac, $d));
                $dateStr = $date->format('Y-m-d');
                $dayOfWeek = (int) $date->format('N');
                $isWeekend = $dayOfWeek >= 6;
                $isSwieto = isset($swieta[$dateStr]);

                if ($isWeekend || $isSwieto) {
                    $typ = $typWolne;
                } else {
                    $typ = $typZmiana;
                }

                if (!$typ) {
                    continue;
                }

                $wpis = $grafikWpisRepo->findOneByUserDataDepartament($user, $date, $departament);
                if (!$wpis) {
                    $wpis = new GrafikWpis();
                    $wpis->setUser($user);
                    $wpis->setDepartament($departament);
                    $wpis->setData($date);
                    $wpis->setCreatedBy($currentUser);
                    $em->persist($wpis);
                }
                $wpis->setTypZmiany($typ);
                $count++;
            }
        }

        $em->flush();

        return new JsonResponse(['success' => true, 'count' => $count]);
    }

    // ─── Helpery (przeniesione z hosta, uniezależnione od User) ───

    /**
     * @return Departament[]
     */
    private function getUserDepartaments(PlanerUserInterface $user, UserDepartamentRepository $repo): array
    {
        $uds = $repo->findBy(['user' => $user]);
        $list = [];
        foreach ($uds as $ud) {
            $list[] = $ud->getDepartament();
        }
        return $list;
    }

    private function getUserGlownyDepartament(PlanerUserInterface $user, UserDepartamentRepository $repo): ?Departament
    {
        $uds = $repo->findBy(['user' => $user]);
        foreach ($uds as $ud) {
            if ($ud->isCzyGlowny()) {
                return $ud->getDepartament();
            }
        }
        return null;
    }

    private function isUserSzefDepartamentu(PlanerUserInterface $user, Departament $departament, UserDepartamentRepository $repo): bool
    {
        $ud = $repo->findOneBy(['user' => $user, 'departament' => $departament]);
        return $ud !== null && $ud->isCzySzef();
    }

    private function isGlownyDepartamentForUser(PlanerUserInterface $user, Departament $departament, UserDepartamentRepository $repo): bool
    {
        $ud = $repo->findOneBy(['user' => $user, 'departament' => $departament]);
        return $ud !== null && $ud->isCzyGlowny();
    }

    private function polishDayName(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            1 => 'Pn',
            2 => 'Wt',
            3 => 'Śr',
            4 => 'Cz',
            5 => 'Pt',
            6 => 'So',
            7 => 'Nd',
        };
    }

    private function polishMonthName(int $month): string
    {
        return match ($month) {
            1 => 'Styczeń',
            2 => 'Luty',
            3 => 'Marzec',
            4 => 'Kwiecień',
            5 => 'Maj',
            6 => 'Czerwiec',
            7 => 'Lipiec',
            8 => 'Sierpień',
            9 => 'Wrzesień',
            10 => 'Październik',
            11 => 'Listopad',
            12 => 'Grudzień',
        };
    }
}
