<?php

namespace Planer\PlanerBundle\Entity;

use Planer\PlanerBundle\Model\PlanerUserInterface;
use Planer\PlanerBundle\Repository\PodanieUrlopoweRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PodanieUrlopoweRepository::class)]
#[ORM\Table(name: 'planer_podanie_urlopowe')]
class PodanieUrlopowe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PlanerUserInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private object $user;

    #[ORM\ManyToOne(targetEntity: Departament::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Departament $departament;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $dataOd;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $dataDo;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $zastepca = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $telefon = null;

    #[ORM\ManyToOne(targetEntity: TypZmiany::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TypZmiany $typZmiany = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $daneExtra = null;

    #[ORM\ManyToOne(targetEntity: TypPodania::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TypPodania $typPodania = null;

    #[ORM\ManyToOne(targetEntity: RodzajUrlopu::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RodzajUrlopu $rodzajUrlopu = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $uzasadnienie = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $podpis = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

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

    public function getDepartament(): Departament
    {
        return $this->departament;
    }

    public function setDepartament(Departament $departament): static
    {
        $this->departament = $departament;
        return $this;
    }

    public function getDataOd(): \DateTimeInterface
    {
        return $this->dataOd;
    }

    public function setDataOd(\DateTimeInterface $dataOd): static
    {
        $this->dataOd = $dataOd;
        return $this;
    }

    public function getDataDo(): \DateTimeInterface
    {
        return $this->dataDo;
    }

    public function setDataDo(\DateTimeInterface $dataDo): static
    {
        $this->dataDo = $dataDo;
        return $this;
    }

    public function getZastepca(): ?string
    {
        return $this->zastepca;
    }

    public function setZastepca(?string $zastepca): static
    {
        $this->zastepca = $zastepca;
        return $this;
    }

    public function getTelefon(): ?string
    {
        return $this->telefon;
    }

    public function setTelefon(?string $telefon): static
    {
        $this->telefon = $telefon;
        return $this;
    }

    public function getTypZmiany(): ?TypZmiany
    {
        return $this->typZmiany;
    }

    public function setTypZmiany(?TypZmiany $typZmiany): static
    {
        $this->typZmiany = $typZmiany;
        return $this;
    }

    public function getDaneExtra(): ?array
    {
        return $this->daneExtra;
    }

    public function setDaneExtra(?array $daneExtra): static
    {
        $this->daneExtra = $daneExtra;
        return $this;
    }

    public function getTypPodania(): ?TypPodania
    {
        return $this->typPodania;
    }

    public function setTypPodania(?TypPodania $typPodania): static
    {
        $this->typPodania = $typPodania;
        return $this;
    }

    public function getRodzajUrlopu(): ?RodzajUrlopu
    {
        return $this->rodzajUrlopu;
    }

    public function setRodzajUrlopu(?RodzajUrlopu $rodzajUrlopu): static
    {
        $this->rodzajUrlopu = $rodzajUrlopu;
        return $this;
    }

    public function getUzasadnienie(): ?string
    {
        return $this->uzasadnienie;
    }

    public function setUzasadnienie(?string $uzasadnienie): static
    {
        $this->uzasadnienie = $uzasadnienie;
        return $this;
    }

    public function getPodpis(): ?string
    {
        return $this->podpis;
    }

    public function setPodpis(?string $podpis): static
    {
        $this->podpis = $podpis;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
