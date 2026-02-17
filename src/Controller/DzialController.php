<?php

namespace Planer\PlanerBundle\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use Planer\PlanerBundle\Entity\Departament;
use Planer\PlanerBundle\Model\PlanerUserInterface;
use Planer\PlanerBundle\Repository\GrafikWpisRepository;
use Planer\PlanerBundle\Repository\PlanerUstawieniaRepository;
use Planer\PlanerBundle\Repository\UserDepartamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/planer/dzial')]
#[IsGranted('ROLE_USER')]
class DzialController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    // ──────────────────────────────────────
    //  PRACOWNICY DZIAŁU
    // ──────────────────────────────────────

    #[Route('/pracownicy/{departamentId}', name: 'grafik_dzial_pracownicy', methods: ['GET'],
        defaults: ['departamentId' => null],
        requirements: ['departamentId' => '\d+'])]
    public function pracownicy(
        ?int $departamentId,
        UserDepartamentRepository $udRepo,
    ): Response {
        /** @var PlanerUserInterface $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $szefDepts = $this->getSzefDepartamenty($currentUser, $udRepo);
        if (!$isAdmin && empty($szefDepts)) {
            throw $this->createAccessDeniedException('Brak uprawnień.');
        }

        if ($isAdmin) {
            $dostepneDepts = $this->em->getRepository(Departament::class)
                ->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']);
        } else {
            $dostepneDepts = $szefDepts;
        }

        if (empty($dostepneDepts)) {
            throw $this->createNotFoundException('Brak departamentów.');
        }

        $dept = null;
        if ($departamentId) {
            $dept = $this->em->getRepository(Departament::class)->find($departamentId);
            if ($dept && !$isAdmin) {
                $hasAccess = false;
                foreach ($dostepneDepts as $d) {
                    if ($d->getId() === $dept->getId()) {
                        $hasAccess = true;
                        break;
                    }
                }
                if (!$hasAccess) {
                    $dept = null;
                }
            }
        }
        if (!$dept) {
            $dept = $dostepneDepts[0];
        }

        $userDepartamenty = $udRepo->findUsersForDepartament($dept);

        return $this->render('@Planer/dzial/pracownicy.html.twig', [
            'dept' => $dept,
            'dostepneDepts' => $dostepneDepts,
            'userDepartamenty' => $userDepartamenty,
        ]);
    }

    #[Route('/pracownicy/{departamentId}/save', name: 'grafik_dzial_pracownicy_save', methods: ['POST'],
        requirements: ['departamentId' => '\d+'])]
    public function pracownicySave(
        int $departamentId,
        Request $request,
        UserDepartamentRepository $udRepo,
    ): Response {
        /** @var PlanerUserInterface $currentUser */
        $currentUser = $this->getUser();

        $dept = $this->em->getRepository(Departament::class)->find($departamentId);
        if (!$dept) {
            throw $this->createNotFoundException('Departament nie istnieje.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isUserSzefDepartamentu($currentUser, $dept, $udRepo)) {
            throw $this->createAccessDeniedException('Brak uprawnień.');
        }

        $userDepartamenty = $udRepo->findUsersForDepartament($dept);
        $userClass = $this->getParameter('planer.user_class');

        $postedAdres = $request->request->all('adres');
        $postedUrlop = $request->request->all('urlop');
        $postedKolejnosc = $request->request->all('kolejnosc');

        foreach ($userDepartamenty as $ud) {
            $user = $ud->getUser();
            $userId = $user->getId();

            if (array_key_exists($userId, $postedAdres)) {
                $user->setAdres(trim($postedAdres[$userId]) ?: null);
            }
            if (array_key_exists($userId, $postedUrlop)) {
                $val = (int) $postedUrlop[$userId];
                if ($val > 0 && $val <= 100) {
                    $user->setIloscDniUrlopuWRoku($val);
                }
            }
            if (array_key_exists($userId, $postedKolejnosc)) {
                $ud->setKolejnosc((int) $postedKolejnosc[$userId]);
            }
        }

        $this->em->flush();

        $this->addFlash('success', 'Dane pracowników zostały zapisane.');

        return $this->redirectToRoute('grafik_dzial_pracownicy', ['departamentId' => $departamentId]);
    }

    // ──────────────────────────────────────
    //  RAPORT URLOPOWY PDF
    // ──────────────────────────────────────

    #[Route('/{departamentId}/{rok}/raport-pdf', name: 'grafik_dzial_raport_pdf', methods: ['GET'],
        requirements: ['departamentId' => '\d+', 'rok' => '\d{4}'])]
    public function raportPdf(
        int $departamentId,
        int $rok,
        UserDepartamentRepository $udRepo,
        GrafikWpisRepository $gwRepo,
        Environment $twig,
        PlanerUstawieniaRepository $ustawieniaRepo,
    ): Response {
        $firmaNazwa = $ustawieniaRepo->getSettings()->getFirmaNazwa();
        /** @var PlanerUserInterface $currentUser */
        $currentUser = $this->getUser();

        $dept = $this->em->getRepository(Departament::class)->find($departamentId);
        if (!$dept) {
            throw $this->createNotFoundException('Departament nie istnieje.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isUserSzefDepartamentu($currentUser, $dept, $udRepo)) {
            throw $this->createAccessDeniedException('Brak uprawnień.');
        }

        $userDepartamenty = $udRepo->findUsersForDepartament($dept, false);
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

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * @return Departament[]
     */
    private function getSzefDepartamenty(PlanerUserInterface $user, UserDepartamentRepository $udRepo): array
    {
        $uds = $udRepo->findBy(['user' => $user]);
        $depts = [];
        foreach ($uds as $ud) {
            if ($ud->isCzySzef()) {
                $depts[] = $ud->getDepartament();
            }
        }
        return $depts;
    }

    private function isUserSzefDepartamentu(PlanerUserInterface $user, Departament $departament, UserDepartamentRepository $udRepo): bool
    {
        $ud = $udRepo->findOneBy(['user' => $user, 'departament' => $departament]);
        return $ud !== null && $ud->isCzySzef();
    }

    /**
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
}
