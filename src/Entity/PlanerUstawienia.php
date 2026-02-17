<?php

namespace Planer\PlanerBundle\Entity;

use Planer\PlanerBundle\Repository\PlanerUstawieniaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanerUstawieniaRepository::class)]
#[ORM\Table(name: 'planer_ustawienia')]
class PlanerUstawienia
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private string $firmaNazwa = '';

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    private string $firmaAdres = '';

    #[ORM\ManyToOne(targetEntity: TypZmiany::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TypZmiany $autoPlanZmiana = null;

    #[ORM\ManyToOne(targetEntity: TypZmiany::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TypZmiany $autoPlanWolne = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getFirmaNazwa(): string
    {
        return $this->firmaNazwa;
    }

    public function setFirmaNazwa(string $firmaNazwa): static
    {
        $this->firmaNazwa = $firmaNazwa;
        return $this;
    }

    public function getFirmaAdres(): string
    {
        return $this->firmaAdres;
    }

    public function setFirmaAdres(string $firmaAdres): static
    {
        $this->firmaAdres = $firmaAdres;
        return $this;
    }

    public function getAutoPlanZmiana(): ?TypZmiany
    {
        return $this->autoPlanZmiana;
    }

    public function setAutoPlanZmiana(?TypZmiany $autoPlanZmiana): static
    {
        $this->autoPlanZmiana = $autoPlanZmiana;
        return $this;
    }

    public function getAutoPlanWolne(): ?TypZmiany
    {
        return $this->autoPlanWolne;
    }

    public function setAutoPlanWolne(?TypZmiany $autoPlanWolne): static
    {
        $this->autoPlanWolne = $autoPlanWolne;
        return $this;
    }
}
