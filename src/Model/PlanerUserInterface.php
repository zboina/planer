<?php

namespace Planer\PlanerBundle\Model;

use Doctrine\Common\Collections\Collection;
use Planer\PlanerBundle\Entity\Departament;
use Planer\PlanerBundle\Entity\UserDepartament;

/**
 * Interface that your User entity must implement.
 *
 * The easiest way: add `use PlanerUserTrait;` to your User class
 * — it provides all these methods automatically.
 */
interface PlanerUserInterface
{
    public function getId(): ?int;

    public function getFullName(): ?string;

    public function getAdres(): ?string;

    public function setAdres(?string $adres): static;

    public function getIloscDniUrlopuWRoku(): int;

    public function setIloscDniUrlopuWRoku(int $iloscDniUrlopuWRoku): static;

    /** @return Collection<int, UserDepartament> */
    public function getUserDepartamenty(): Collection;

    public function getGlownyDepartament(): ?Departament;

    /** @return Departament[] */
    public function getDepartamentList(): array;
}
