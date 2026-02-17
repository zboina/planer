<?php

namespace Planer\PlanerBundle\Entity;

use Planer\PlanerBundle\Model\PlanerUserInterface;
use Planer\PlanerBundle\Repository\UserDepartamentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserDepartamentRepository::class)]
#[ORM\Table(name: 'user_departament')]
#[ORM\UniqueConstraint(name: 'uq_user_departament', columns: ['user_id', 'departament_id'])]
class UserDepartament
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PlanerUserInterface::class, inversedBy: 'userDepartamenty')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private object $user;

    #[ORM\ManyToOne(targetEntity: Departament::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Departament $departament;

    #[ORM\Column(options: ['default' => false])]
    private bool $czyGlowny = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $czySzef = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $kolejnosc = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $czyUkryty = false;

    public function __construct(object $user, Departament $departament)
    {
        $this->user = $user;
        $this->departament = $departament;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): object
    {
        return $this->user;
    }

    public function getDepartament(): Departament
    {
        return $this->departament;
    }

    public function isCzyGlowny(): bool
    {
        return $this->czyGlowny;
    }

    public function setCzyGlowny(bool $czyGlowny): static
    {
        $this->czyGlowny = $czyGlowny;
        return $this;
    }

    public function isCzySzef(): bool
    {
        return $this->czySzef;
    }

    public function setCzySzef(bool $czySzef): static
    {
        $this->czySzef = $czySzef;
        return $this;
    }

    public function getKolejnosc(): int
    {
        return $this->kolejnosc;
    }

    public function setKolejnosc(int $kolejnosc): static
    {
        $this->kolejnosc = $kolejnosc;
        return $this;
    }

    public function isCzyUkryty(): bool
    {
        return $this->czyUkryty;
    }

    public function setCzyUkryty(bool $czyUkryty): static
    {
        $this->czyUkryty = $czyUkryty;
        return $this;
    }
}
