<?php

namespace Planer\PlanerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Stores planer-specific data for each user (adres, vacation days).
 * Created automatically — no changes to the User class required.
 */
#[ORM\Entity]
#[ORM\Table(name: 'planer_user_profile')]
class PlanerUserProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id', unique: true)]
    private int $userId;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $adres = null;

    #[ORM\Column(name: 'first_name', length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'ilosc_dni_urlopu_w_roku', options: ['default' => 26])]
    private int $iloscDniUrlopuWRoku = 26;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }
}
