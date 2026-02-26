<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Planer\PlanerBundle\Entity\PodanieLog;
use Planer\PlanerBundle\Entity\PodanieUrlopowe;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

#[AsEventListener(event: 'workflow.podanie_workflow.completed', method: 'onCompleted')]
class PodanieWorkflowLogListener
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function onCompleted(CompletedEvent $event): void
    {
        /** @var PodanieUrlopowe $podanie */
        $podanie = $event->getSubject();
        $transition = $event->getTransition();

        $froms = $transition->getFroms();
        $tos = $transition->getTos();

        $log = new PodanieLog();
        $log->setPodanie($podanie);
        $log->setTransition($transition->getName());
        $log->setFromStatus($froms[0] ?? '');
        $log->setToStatus($tos[0] ?? '');

        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user !== null) {
            $log->setUser($user);
        }

        // Capture rejection comment
        if (str_starts_with($transition->getName(), 'odrzuc') && $podanie->getKomentarzOdrzucenia()) {
            $log->setKomentarz($podanie->getKomentarzOdrzucenia());
        }

        $this->em->persist($log);
    }
}
