<?php

namespace Planer\PlanerBundle\Twig;

use Planer\PlanerBundle\Service\PlanerUserResolver;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class PlanerExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private string $baseTemplate,
        private string $logoutRoute,
        private PlanerUserResolver $resolver,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'planer_base_template' => $this->baseTemplate,
            'planer_logout_route' => $this->logoutRoute,
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('planer_name', [$this, 'getUserName']),
            new TwigFunction('planer_adres', [$this, 'getUserAdres']),
            new TwigFunction('planer_urlop_limit', [$this, 'getUserUrlopLimit']),
        ];
    }

    public function getUserName(object $user): string
    {
        return $this->resolver->getFullName($user);
    }

    public function getUserAdres(object $user): ?string
    {
        return $this->resolver->getAdres($user);
    }

    public function getUserUrlopLimit(object $user): int
    {
        return $this->resolver->getIloscDniUrlopu($user);
    }
}
