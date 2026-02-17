<?php

namespace Planer\PlanerBundle\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use Planer\PlanerBundle\Entity\PodanieUrlopowe;
use Planer\PlanerBundle\Entity\RodzajUrlopu;
use Planer\PlanerBundle\Entity\TypPodania;
use Planer\PlanerBundle\Entity\TypZmiany;
use Planer\PlanerBundle\Repository\GrafikWpisRepository;
use Planer\PlanerBundle\Repository\PlanerUstawieniaRepository;
use Planer\PlanerBundle\Repository\PodanieUrlopoweRepository;
use Planer\PlanerBundle\Repository\UserDepartamentRepository;
use Planer\PlanerBundle\Service\PlanerUserResolver;
use Planer\PlanerBundle\Service\PlaceholderReplacerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/planer/podanie')]
#[IsGranted('ROLE_USER')]
class PodanieController extends AbstractController
{
    private const SZABLONY = ['urlop', 'praca_zdalna', 'nadgodziny', 'opieka'];
    private const SZABLON_LABELS = [
        'urlop' => 'Podanie o urlop',
        'praca_zdalna' => 'Wniosek o pracę zdalną',
        'nadgodziny' => 'Wniosek o odbiór nadgodzin',
        'opieka' => 'Opieka nad dzieckiem',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlanerUserResolver $resolver,
    ) {
    }

    #[Route('/lista', name: 'grafik_podanie_lista', methods: ['GET'])]
    public function lista(
        PodanieUrlopoweRepository $podanieRepo,
        UserDepartamentRepository $udRepo,
    ): Response {
        $currentUser = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $podania = $podanieRepo->findBy([], ['createdAt' => 'DESC']);
        } elseif ($this->isUserSzefAnyDept($currentUser, $udRepo)) {
            $depts = $this->getSzefDepartamenty($currentUser, $udRepo);
            $podania = $podanieRepo->findByDepartamenty($depts);
        } else {
            $podania = $podanieRepo->findByUser($currentUser);
        }

        return $this->render('@Planer/podanie/lista.html.twig', [
            'podania' => $podania,
            'currentUser' => $currentUser,
        ]);
    }

    #[Route('/new/{userId}/{rok}', name: 'grafik_podanie_form', methods: ['GET'],
        requirements: ['userId' => '\d+', 'rok' => '\d{4}'])]
    public function form(
        int $userId,
        int $rok,
        Request $request,
        GrafikWpisRepository $gwRepo,
        UserDepartamentRepository $udRepo,
    ): Response {
        $user = $this->resolveTargetUser($userId, $udRepo);

        $dept = $this->resolver->getGlownyDepartament($user);
        if (!$dept) {
            $depts = $this->resolver->getDepartamentList($user);
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
            // FALLBACK: old string-based logic
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

        $currentUser = $this->getUser();
        $editAdres = ($currentUser->getId() === $user->getId());

        $templateVars = [
            'user' => $user,
            'rok' => $rok,
            'dept' => $dept,
            'dataOd' => $dataOd,
            'dataDo' => $dataDo,
            'editAdres' => $editAdres,
            'szablon' => $szablon,
            'szablonLabel' => $szablonLabel,
            'typZmianyId' => $typZmianyId,
            'polaFormularza' => $polaFormularza,
        ];

        // Load data for dynamic or legacy fields
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

        return $this->render('@Planer/podanie/form.html.twig', $templateVars);
    }

    #[Route('/new/{userId}/{rok}', name: 'grafik_podanie_generate', methods: ['POST'],
        requirements: ['userId' => '\d+', 'rok' => '\d{4}'])]
    public function generate(
        int $userId,
        int $rok,
        Request $request,
        UserDepartamentRepository $udRepo,
    ): Response {
        $user = $this->resolveTargetUser($userId, $udRepo);

        $dept = $this->resolver->getGlownyDepartament($user);
        if (!$dept) {
            $depts = $this->resolver->getDepartamentList($user);
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

        $currentUser = $this->getUser();
        if ($currentUser->getId() === $user->getId()) {
            $adres = trim($request->request->getString('adres'));
            if ($adres) {
                $this->resolver->setAdres($user, $adres);
            }
        }

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
            if (in_array('uzasadnienie', $polaFormularza, true)) {
                $podanie->setUzasadnienie(trim($request->request->getString('uzasadnienie')) ?: null);
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
            // FALLBACK: old logic
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

        $this->addFlash('success', 'Podanie zostało zapisane.');

        return $this->redirectToRoute('grafik_podanie_lista');
    }

    #[Route('/{id}/pdf', name: 'grafik_podanie_pdf', methods: ['GET'],
        requirements: ['id' => '\d+'])]
    public function pdf(
        int $id,
        Environment $twig,
        UserDepartamentRepository $udRepo,
        PlanerUstawieniaRepository $ustawieniaRepo,
        PlaceholderReplacerService $replacerService,
    ): Response {
        $settings = $ustawieniaRepo->getSettings();
        $podanie = $this->em->getRepository(PodanieUrlopowe::class)->find($id);
        if (!$podanie) {
            throw $this->createNotFoundException('Podanie nie istnieje.');
        }

        $this->denyUnlessCanAccess($podanie, $udRepo);

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

    #[Route('/{id}/delete', name: 'grafik_podanie_delete', methods: ['POST'],
        requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        UserDepartamentRepository $udRepo,
    ): Response {
        $podanie = $this->em->getRepository(PodanieUrlopowe::class)->find($id);
        if ($podanie) {
            $this->denyUnlessCanAccess($podanie, $udRepo);
            $this->em->remove($podanie);
            $this->em->flush();
            $this->addFlash('success', 'Podanie zostało usunięte.');
        }

        return $this->redirectToRoute('grafik_podanie_lista');
    }

    // ─── Access control helpers ───────────────────────────────

    private function resolveTargetUser(int $userId, UserDepartamentRepository $udRepo): object
    {
        $userClass = $this->getParameter('planer.user_class');
        $user = $this->em->getRepository($userClass)->find($userId);
        if (!$user) {
            throw $this->createNotFoundException('Użytkownik nie istnieje.');
        }

        $currentUser = $this->getUser();

        if ($currentUser->getId() === $user->getId()) {
            return $user;
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $user;
        }

        if ($this->canManageUser($currentUser, $user, $udRepo)) {
            return $user;
        }

        throw $this->createAccessDeniedException('Brak uprawnień.');
    }

    private function denyUnlessCanAccess(PodanieUrlopowe $podanie, UserDepartamentRepository $udRepo): void
    {
        $currentUser = $this->getUser();

        if ($currentUser->getId() === $podanie->getUser()->getId()) {
            return;
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        if ($this->isUserSzefDepartamentu($currentUser, $podanie->getDepartament(), $udRepo)) {
            return;
        }

        throw $this->createAccessDeniedException('Brak uprawnień.');
    }

    private function canManageUser(object $szef, object $targetUser, UserDepartamentRepository $udRepo): bool
    {
        $szefDepts = $this->getSzefDepartamenty($szef, $udRepo);
        foreach ($szefDepts as $dept) {
            $ud = $udRepo->findOneBy(['user' => $targetUser, 'departament' => $dept]);
            if ($ud) {
                return true;
            }
        }
        return false;
    }

    private function isUserSzefAnyDept(object $user, UserDepartamentRepository $udRepo): bool
    {
        $uds = $udRepo->findBy(['user' => $user]);
        foreach ($uds as $ud) {
            if ($ud->isCzySzef()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return \Planer\PlanerBundle\Entity\Departament[]
     */
    private function getSzefDepartamenty(object $user, UserDepartamentRepository $udRepo): array
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

    private function isUserSzefDepartamentu(object $user, \Planer\PlanerBundle\Entity\Departament $departament, UserDepartamentRepository $udRepo): bool
    {
        $ud = $udRepo->findOneBy(['user' => $user, 'departament' => $departament]);
        return $ud !== null && $ud->isCzySzef();
    }
}
