<?php

namespace Planer\PlanerBundle\Controller;

use Planer\PlanerBundle\Entity\WorkflowKrok;
use Planer\PlanerBundle\Repository\PlanerRolaRepository;
use Planer\PlanerBundle\Repository\PodanieUrlopoweRepository;
use Planer\PlanerBundle\Repository\UserDepartamentRepository;
use Planer\PlanerBundle\Service\PodanieWorkflowFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/planer')]
#[IsGranted('ROLE_USER')]
class PanelController extends AbstractController
{
    #[Route('/panel', name: 'planer_panel', methods: ['GET'])]
    public function panel(
        PodanieUrlopoweRepository $podanieRepo,
        UserDepartamentRepository $udRepo,
        PodanieWorkflowFactory $workflowFactory,
        PlanerRolaRepository $rolaRepo,
    ): Response {
        $currentUser = $this->getUser();

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isSzef = $this->isUserSzefAnyDept($currentUser, $udRepo);
        $hasGlobalRole = !empty($rolaRepo->getRoles($currentUser));

        $podaniaCount = 0;
        if ($isAdmin || $isSzef || $hasGlobalRole) {
            $podaniaCount = $this->countPodaniaDoAkceptacji(
                $currentUser, $podanieRepo, $udRepo, $workflowFactory, $rolaRepo, $isAdmin, $isSzef,
            );
        }

        return $this->render('@Planer/panel/index.html.twig', [
            'isAdmin' => $isAdmin,
            'hasWorkflowRole' => $isAdmin || $isSzef || $hasGlobalRole,
            'podaniaCount' => $podaniaCount,
        ]);
    }

    private function countPodaniaDoAkceptacji(
        object $currentUser,
        PodanieUrlopoweRepository $podanieRepo,
        UserDepartamentRepository $udRepo,
        PodanieWorkflowFactory $workflowFactory,
        PlanerRolaRepository $rolaRepo,
        bool $isAdmin,
        bool $isSzef,
    ): int {
        $count = 0;
        $steps = $workflowFactory->getActiveSteps();

        if ($isAdmin) {
            // Admin sees all pending podania at every step
            $prevStatus = 'zlozony';
            foreach ($steps as $i => $step) {
                $count += count($podanieRepo->findDoAkceptacji($prevStatus));
                $isLast = ($i === count($steps) - 1);
                $prevStatus = $isLast ? 'zatwierdzony' : $step->getKey() . '_ok';
            }
        } else {
            $prevStatus = 'zlozony';
            foreach ($steps as $i => $step) {
                if ($step->getType() === WorkflowKrok::TYPE_DEPARTMENT && $isSzef) {
                    $depts = $this->getSzefDepartamenty($currentUser, $udRepo);
                    $count += count($podanieRepo->findDoAkceptacji($prevStatus, $depts));
                } elseif ($step->getType() === WorkflowKrok::TYPE_GLOBAL && $rolaRepo->hasRole($currentUser, $step->getKey())) {
                    $count += count($podanieRepo->findDoAkceptacji($prevStatus));
                }
                $isLast = ($i === count($steps) - 1);
                $prevStatus = $isLast ? 'zatwierdzony' : $step->getKey() . '_ok';
            }
        }

        return $count;
    }

    private function isUserSzefAnyDept(object $user, UserDepartamentRepository $udRepo): bool
    {
        foreach ($udRepo->findBy(['user' => $user]) as $ud) {
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
        $depts = [];
        foreach ($udRepo->findBy(['user' => $user]) as $ud) {
            if ($ud->isCzySzef()) {
                $depts[] = $ud->getDepartament();
            }
        }
        return $depts;
    }
}
