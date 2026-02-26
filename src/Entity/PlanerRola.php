<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Entity;

use Planer\PlanerBundle\Model\PlanerUserInterface;
use Planer\PlanerBundle\Repository\PlanerRolaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanerRolaRepository::class)]
#[ORM\Table(name: 'planer_user_rola')]
#[ORM\UniqueConstraint(columns: ['user_id', 'rola'])]
class PlanerRola
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PlanerUserInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private object $user;

    #[ORM\Column(length: 30)]
    private string $rola;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): object
    {
        return $this->user;
    }

    public function setUser(object $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getRola(): string
    {
        return $this->rola;
    }

    public function setRola(string $rola): static
    {
        $this->rola = $rola;
        return $this;
    }
}
