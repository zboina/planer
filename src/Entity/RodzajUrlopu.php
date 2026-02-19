<?php

namespace Planer\PlanerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'planer_rodzaj_urlopu')]
class RodzajUrlopu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $nazwa;

    #[ORM\Column(options: ['default' => 0])]
    private int $kolejnosc = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNazwa(): string
    {
        return $this->nazwa;
    }

    public function setNazwa(string $nazwa): static
    {
        $this->nazwa = $nazwa;
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
}
