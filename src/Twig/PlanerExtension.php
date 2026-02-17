<?php

namespace Planer\PlanerBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class PlanerExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private string $baseTemplate)
    {
    }

    public function getGlobals(): array
    {
        return ['planer_base_template' => $this->baseTemplate];
    }
}
