<?php

namespace Planer\PlanerBundle\Entity;

use Planer\PlanerBundle\Repository\PlanerModulRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanerModulRepository::class)]
#[ORM\Table(name: 'planer_modul')]
class PlanerModul
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $kod;

    #[ORM\Column(length: 100)]
    private string $nazwa;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $opis = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ikona = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $aktywny = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $kolejnosc = 0;

    #[ORM\Column(length: 20, options: ['default' => 'wszyscy'])]
    private string $trybDostepu = 'wszyscy';

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $dozwoloneRole = [];

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $dozwoleniUserIds = [];

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $dozwoloneDepartamentyIds = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKod(): string
    {
        return $this->kod;
    }

    public function setKod(string $kod): static
    {
        $this->kod = $kod;
        return $this;
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

    public function getOpis(): ?string
    {
        return $this->opis;
    }

    public function setOpis(?string $opis): static
    {
        $this->opis = $opis;
        return $this;
    }

    public function getIkona(): ?string
    {
        return $this->ikona;
    }

    public function setIkona(?string $ikona): static
    {
        $this->ikona = $ikona;
        return $this;
    }

    public function isAktywny(): bool
    {
        return $this->aktywny;
    }

    public function setAktywny(bool $aktywny): static
    {
        $this->aktywny = $aktywny;
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

    public function getTrybDostepu(): string
    {
        return $this->trybDostepu;
    }

    public function setTrybDostepu(string $trybDostepu): static
    {
        $this->trybDostepu = $trybDostepu;
        return $this;
    }

    public function getDozwoloneRole(): array
    {
        return $this->dozwoloneRole;
    }

    public function setDozwoloneRole(array $dozwoloneRole): static
    {
        $this->dozwoloneRole = $dozwoloneRole;
        return $this;
    }

    public function getDozwoleniUserIds(): array
    {
        return $this->dozwoleniUserIds;
    }

    public function setDozwoleniUserIds(array $dozwoleniUserIds): static
    {
        $this->dozwoleniUserIds = $dozwoleniUserIds;
        return $this;
    }

    public function getDozwoloneDepartamentyIds(): array
    {
        return $this->dozwoloneDepartamentyIds;
    }

    public function setDozwoloneDepartamentyIds(array $dozwoloneDepartamentyIds): static
    {
        $this->dozwoloneDepartamentyIds = $dozwoloneDepartamentyIds;
        return $this;
    }
}
