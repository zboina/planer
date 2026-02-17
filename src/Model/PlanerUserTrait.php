<?php

namespace Planer\PlanerBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Planer\PlanerBundle\Entity\Departament;
use Planer\PlanerBundle\Entity\UserDepartament;

/**
 * Trait to add to your User entity to satisfy PlanerUserInterface.
 *
 * Usage:
 *   1. Add `implements \Planer\PlanerBundle\Model\PlanerUserInterface` to your User class
 *   2. Add `use \Planer\PlanerBundle\Model\PlanerUserTrait;` inside the class
 *   3. Run `php bin/console doctrine:migrations:diff` and `migrate`
 */
trait PlanerUserTrait
{
    /** @var Collection<int, UserDepartament> */
    #[ORM\OneToMany(targetEntity: UserDepartament::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $userDepartamenty;

    #[ORM\Column(name: 'ilosc_dni_urlopu_w_roku', options: ['default' => 26])]
    private int $iloscDniUrlopuWRoku = 26;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $adres = null;

    private function initPlanerCollections(): void
    {
        if (!isset($this->userDepartamenty)) {
            $this->userDepartamenty = new ArrayCollection();
        }
    }

    public function getFullName(): ?string
    {
        // Try fullName property directly
        if (property_exists($this, 'fullName') && $this->fullName) {
            return $this->fullName;
        }

        // Try firstName + lastName (standard Symfony user)
        if (property_exists($this, 'firstName') && property_exists($this, 'lastName')) {
            $name = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        // Try imie + nazwisko (Polish naming convention)
        if (property_exists($this, 'imie') && property_exists($this, 'nazwisko')) {
            $name = trim(($this->imie ?? '') . ' ' . ($this->nazwisko ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        // Try name property
        if (property_exists($this, 'name') && $this->name) {
            return $this->name;
        }

        // Fallback to getUserIdentifier (email, username, etc.)
        if (method_exists($this, 'getUserIdentifier')) {
            return $this->getUserIdentifier();
        }

        return null;
    }

    public function getAdres(): ?string
    {
        return $this->adres;
    }

    public function setAdres(?string $adres): static
    {
        $this->adres = $adres;
        return $this;
    }

    public function getIloscDniUrlopuWRoku(): int
    {
        return $this->iloscDniUrlopuWRoku;
    }

    public function setIloscDniUrlopuWRoku(int $iloscDniUrlopuWRoku): static
    {
        $this->iloscDniUrlopuWRoku = $iloscDniUrlopuWRoku;
        return $this;
    }

    /** @return Collection<int, UserDepartament> */
    public function getUserDepartamenty(): Collection
    {
        $this->initPlanerCollections();
        return $this->userDepartamenty;
    }

    public function getGlownyDepartament(): ?Departament
    {
        $this->initPlanerCollections();
        foreach ($this->userDepartamenty as $ud) {
            if ($ud->isCzyGlowny()) {
                return $ud->getDepartament();
            }
        }
        return null;
    }

    public function isSzefDepartamentu(Departament $departament): bool
    {
        $this->initPlanerCollections();
        foreach ($this->userDepartamenty as $ud) {
            if ($ud->getDepartament()->getId() === $departament->getId() && $ud->isCzySzef()) {
                return true;
            }
        }
        return false;
    }

    /** @return Departament[] */
    public function getDepartamentList(): array
    {
        $this->initPlanerCollections();
        $list = [];
        foreach ($this->userDepartamenty as $ud) {
            $list[] = $ud->getDepartament();
        }
        return $list;
    }
}
