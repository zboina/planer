<?php

namespace Planer\PlanerBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'typ_zmiany')]
class TypZmiany
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $nazwa = null;

    #[ORM\Column(length: 5)]
    private ?string $skrot = null;

    #[ORM\Column(length: 7)]
    private ?string $kolor = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $godzinyOd = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $godzinyDo = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $aktywny = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $kolejnosc = 0;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $skrotKlawiaturowy = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $tylkoGlowny = false;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $szablonPodania = null;

    #[ORM\ManyToOne(targetEntity: SzablonPodania::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SzablonPodania $szablon = null;

    /** @var Collection<int, Departament> */
    #[ORM\ManyToMany(targetEntity: Departament::class)]
    #[ORM\JoinTable(name: 'typ_zmiany_departament')]
    private Collection $departamenty;

    public function __construct()
    {
        $this->departamenty = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNazwa(): ?string
    {
        return $this->nazwa;
    }

    public function setNazwa(string $nazwa): static
    {
        $this->nazwa = $nazwa;
        return $this;
    }

    public function getSkrot(): ?string
    {
        return $this->skrot;
    }

    public function setSkrot(string $skrot): static
    {
        $this->skrot = $skrot;
        return $this;
    }

    public function getKolor(): ?string
    {
        return $this->kolor;
    }

    public function getKolorTekstu(): string
    {
        $hex = ltrim($this->kolor ?? '#000000', '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // W3C relative luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.55 ? '#1e293b' : '#ffffff';
    }

    public function setKolor(string $kolor): static
    {
        $this->kolor = $kolor;
        return $this;
    }

    public function getGodzinyOd(): ?string
    {
        return $this->godzinyOd;
    }

    public function setGodzinyOd(?string $godzinyOd): static
    {
        $this->godzinyOd = $godzinyOd;
        return $this;
    }

    public function getGodzinyDo(): ?string
    {
        return $this->godzinyDo;
    }

    public function setGodzinyDo(?string $godzinyDo): static
    {
        $this->godzinyDo = $godzinyDo;
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

    public function getSkrotKlawiaturowy(): ?string
    {
        return $this->skrotKlawiaturowy;
    }

    public function setSkrotKlawiaturowy(?string $skrotKlawiaturowy): static
    {
        $this->skrotKlawiaturowy = $skrotKlawiaturowy;
        return $this;
    }

    public function isTylkoGlowny(): bool
    {
        return $this->tylkoGlowny;
    }

    public function setTylkoGlowny(bool $tylkoGlowny): static
    {
        $this->tylkoGlowny = $tylkoGlowny;
        return $this;
    }

    /** @return Collection<int, Departament> */
    public function getDepartamenty(): Collection
    {
        return $this->departamenty;
    }

    public function addDepartament(Departament $departament): static
    {
        if (!$this->departamenty->contains($departament)) {
            $this->departamenty->add($departament);
        }
        return $this;
    }

    public function removeDepartament(Departament $departament): static
    {
        $this->departamenty->removeElement($departament);
        return $this;
    }

    /**
     * Czy typ jest dostępny w danym departamencie?
     * Pusta kolekcja = dostępny wszędzie.
     */
    public function isAvailableForDepartament(Departament $departament): bool
    {
        if ($this->departamenty->isEmpty()) {
            return true;
        }
        return $this->departamenty->contains($departament);
    }

    public function getSzablonPodania(): ?string
    {
        return $this->szablonPodania;
    }

    public function setSzablonPodania(?string $szablonPodania): static
    {
        $this->szablonPodania = $szablonPodania;
        return $this;
    }

    public function getSzablon(): ?SzablonPodania
    {
        return $this->szablon;
    }

    public function setSzablon(?SzablonPodania $szablon): static
    {
        $this->szablon = $szablon;
        return $this;
    }

    /** @return int[] */
    public function getDepartamentyIds(): array
    {
        return $this->departamenty->map(fn(Departament $d) => $d->getId())->toArray();
    }
}
