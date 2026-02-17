<?php

namespace Planer\PlanerBundle\Entity;

use Planer\PlanerBundle\Model\PlanerUserInterface;
use Planer\PlanerBundle\Repository\GrafikWpisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GrafikWpisRepository::class)]
#[ORM\Table(name: 'grafik_wpis')]
#[ORM\UniqueConstraint(name: 'uq_grafik_wpis', columns: ['user_id', 'data', 'departament_id'])]
#[ORM\HasLifecycleCallbacks]
class GrafikWpis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PlanerUserInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PlanerUserInterface $user;

    #[ORM\ManyToOne(targetEntity: Departament::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Departament $departament;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $data;

    #[ORM\ManyToOne(targetEntity: TypZmiany::class)]
    #[ORM\JoinColumn(nullable: false)]
    private TypZmiany $typZmiany;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notatka = null;

    #[ORM\ManyToOne(targetEntity: PlanerUserInterface::class)]
    #[ORM\JoinColumn(nullable: false)]
    private PlanerUserInterface $createdBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): PlanerUserInterface
    {
        return $this->user;
    }

    public function setUser(PlanerUserInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getDepartament(): Departament
    {
        return $this->departament;
    }

    public function setDepartament(Departament $departament): static
    {
        $this->departament = $departament;
        return $this;
    }

    public function getData(): \DateTimeInterface
    {
        return $this->data;
    }

    public function setData(\DateTimeInterface $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getTypZmiany(): TypZmiany
    {
        return $this->typZmiany;
    }

    public function setTypZmiany(TypZmiany $typZmiany): static
    {
        $this->typZmiany = $typZmiany;
        return $this;
    }

    public function getNotatka(): ?string
    {
        return $this->notatka;
    }

    public function setNotatka(?string $notatka): static
    {
        $this->notatka = $notatka;
        return $this;
    }

    public function getCreatedBy(): PlanerUserInterface
    {
        return $this->createdBy;
    }

    public function setCreatedBy(PlanerUserInterface $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
