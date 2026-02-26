<?php

namespace Planer\PlanerBundle\Twig;

use Planer\PlanerBundle\Entity\PodanieUrlopowe;
use Planer\PlanerBundle\Service\ModulChecker;
use Planer\PlanerBundle\Service\PlanerUserResolver;
use Planer\PlanerBundle\Service\PodanieStatusProvider;
use Planer\PlanerBundle\Service\PodanieWorkflowFactory;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class PlanerExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private string $baseTemplate,
        private string $logoutRoute,
        private string $adminRole,
        private PlanerUserResolver $resolver,
        private ModulChecker $modulChecker,
        private PodanieWorkflowFactory $workflowFactory,
        private PodanieStatusProvider $statusProvider,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'planer_base_template' => $this->baseTemplate,
            'planer_logout_route' => $this->logoutRoute,
            'planer_admin_role' => $this->adminRole,
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('planer_name', [$this, 'getUserName']),
            new TwigFunction('planer_first_name', [$this, 'getUserFirstName']),
            new TwigFunction('planer_last_name', [$this, 'getUserLastName']),
            new TwigFunction('planer_has_own_name', [$this, 'userHasOwnName']),
            new TwigFunction('planer_adres', [$this, 'getUserAdres']),
            new TwigFunction('planer_urlop_limit', [$this, 'getUserUrlopLimit']),
            new TwigFunction('planer_modul', [$this, 'hasModulAccess']),
            new TwigFunction('podanie_can', [$this, 'podanieCan']),
            new TwigFunction('podanie_status_label', [$this, 'podanieStatusLabel']),
            new TwigFunction('podanie_status_badge', [$this, 'podanieStatusBadge']),
            new TwigFunction('podanie_is_orphan', [$this, 'podanieIsOrphan']),
            new TwigFunction('podanie_next_step', [$this, 'podanieNextStep']),
        ];
    }

    public function hasModulAccess(string $kod): bool
    {
        return $this->modulChecker->hasAccess($kod);
    }

    public function getUserName(object $user): string
    {
        return $this->resolver->getFullName($user);
    }

    public function getUserFirstName(object $user): ?string
    {
        return $this->resolver->getFirstName($user);
    }

    public function getUserLastName(object $user): ?string
    {
        return $this->resolver->getLastName($user);
    }

    public function userHasOwnName(object $user): bool
    {
        return $this->resolver->hasOwnName($user);
    }

    public function getUserAdres(object $user): ?string
    {
        return $this->resolver->getAdres($user);
    }

    public function getUserUrlopLimit(object $user): int
    {
        return $this->resolver->getIloscDniUrlopu($user);
    }

    /**
     * Check if a podanie workflow transition type can fire.
     * Usage: podanie_can(podanie, 'akceptuj'), podanie_can(podanie, 'odrzuc'), podanie_can(podanie, 'anuluj')
     */
    public function podanieCan(PodanieUrlopowe $podanie, string $type): bool
    {
        return $this->workflowFactory->can($podanie, $type);
    }

    public function podanieStatusLabel(string $status): string
    {
        return $this->statusProvider->getLabel($status);
    }

    public function podanieStatusBadge(string $status): string
    {
        return $this->statusProvider->getBadge($status);
    }

    public function podanieIsOrphan(PodanieUrlopowe $podanie): bool
    {
        return $this->workflowFactory->isOrphanStatus($podanie);
    }

    /**
     * Returns the label of the next workflow step waiting for this status.
     * E.g. 'zlozony' → "Szef działu", 'szef_ok' → "Kadry"
     */
    public function podanieNextStep(string $status): ?string
    {
        return $this->statusProvider->getNextStepLabel($status);
    }
}
