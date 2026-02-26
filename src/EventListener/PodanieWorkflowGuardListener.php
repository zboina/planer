<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\EventListener;

use Planer\PlanerBundle\Entity\PodanieUrlopowe;
use Planer\PlanerBundle\Entity\WorkflowKrok;
use Planer\PlanerBundle\Repository\PlanerRolaRepository;
use Planer\PlanerBundle\Repository\UserDepartamentRepository;
use Planer\PlanerBundle\Service\PodanieWorkflowFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * Unified dynamic guard for all podanie workflow transitions.
 * Reads step config (type: department/global) and checks authorization.
 */
#[AsEventListener(event: 'workflow.podanie_workflow.guard', method: 'onGuard')]
class PodanieWorkflowGuardListener
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserDepartamentRepository $udRepo,
        private readonly PlanerRolaRepository $rolaRepo,
        private readonly PodanieWorkflowFactory $workflowFactory,
    ) {
    }

    public function onGuard(GuardEvent $event): void
    {
        // Admin bypasses all guards
        if ($this->authChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user === null) {
            $event->addTransitionBlocker(new TransitionBlocker('Brak zalogowanego użytkownika.', '0'));
            return;
        }

        $transitionName = $event->getTransition()->getName();

        // anuluj — allowed for department-type step roles (szef) for this podanie's department
        if ($transitionName === 'anuluj') {
            $this->guardAnuluj($event, $user);
            return;
        }

        // Extract step key: 'akceptuj_kadry' -> 'kadry', 'odrzuc_szef' -> 'szef', 'pomin_kadry' -> 'kadry'
        $parts = explode('_', $transitionName, 2);
        $stepKey = $parts[1] ?? null;

        if ($stepKey === null) {
            $event->addTransitionBlocker(new TransitionBlocker('Nieznana tranzycja.', '0'));
            return;
        }

        $step = $this->workflowFactory->getStepByKey($stepKey);
        if ($step === null) {
            $event->addTransitionBlocker(new TransitionBlocker('Nieznany krok workflow.', '0'));
            return;
        }

        if ($step->isDepartment()) {
            $this->guardDepartment($event, $user);
        } elseif ($step->isGlobal()) {
            $this->guardGlobal($event, $user, $stepKey);
        }
    }

    private function guardDepartment(GuardEvent $event, object $user): void
    {
        /** @var PodanieUrlopowe $podanie */
        $podanie = $event->getSubject();

        $ud = $this->udRepo->findOneBy([
            'user' => $user,
            'departament' => $podanie->getDepartament(),
        ]);

        if ($ud === null || !$ud->isCzySzef()) {
            $event->addTransitionBlocker(
                new TransitionBlocker('Nie jesteś szefem departamentu tego podania.', '0')
            );
        }
    }

    private function guardGlobal(GuardEvent $event, object $user, string $stepKey): void
    {
        if (!$this->rolaRepo->hasRole($user, $stepKey)) {
            $event->addTransitionBlocker(
                new TransitionBlocker('Brak wymaganej roli.', '0')
            );
        }
    }

    private function guardAnuluj(GuardEvent $event, object $user): void
    {
        /** @var PodanieUrlopowe $podanie */
        $podanie = $event->getSubject();

        // Check if user has any department-type role for this podanie's department
        $ud = $this->udRepo->findOneBy([
            'user' => $user,
            'departament' => $podanie->getDepartament(),
        ]);

        if ($ud !== null && $ud->isCzySzef()) {
            return; // department szef can cancel
        }

        // Check if user has any global workflow role
        $userRoles = $this->rolaRepo->getRoles($user);
        $stepKeys = array_map(fn($s) => $s->getKey(), $this->workflowFactory->getSteps());
        $globalStepKeys = array_map(
            fn($s) => $s->getKey(),
            array_filter($this->workflowFactory->getSteps(), fn($s) => $s->isGlobal())
        );

        foreach ($globalStepKeys as $key) {
            if (in_array($key, $userRoles, true)) {
                return; // has at least one global workflow role
            }
        }

        $event->addTransitionBlocker(
            new TransitionBlocker('Brak uprawnień do anulowania.', '0')
        );
    }
}
