<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Entity;

use Planer\PlanerBundle\Model\PlanerUserInterface;
use Planer\PlanerBundle\Repository\PodanieLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PodanieLogRepository::class)]
#[ORM\Table(name: 'planer_podanie_log')]
class PodanieLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PodanieUrlopowe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PodanieUrlopowe $podanie;

    #[ORM\ManyToOne(targetEntity: PlanerUserInterface::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?object $user = null;

    #[ORM\Column(length: 30)]
    private string $transition;

    #[ORM\Column(length: 20)]
    private string $fromStatus;

    #[ORM\Column(length: 20)]
    private string $toStatus;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $komentarz = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPodanie(): PodanieUrlopowe
    {
        return $this->podanie;
    }

    public function setPodanie(PodanieUrlopowe $podanie): static
    {
        $this->podanie = $podanie;
        return $this;
    }

    public function getUser(): ?object
    {
        return $this->user;
    }

    public function setUser(?object $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }

    public function setTransition(string $transition): static
    {
        $this->transition = $transition;
        return $this;
    }

    public function getFromStatus(): string
    {
        return $this->fromStatus;
    }

    public function setFromStatus(string $fromStatus): static
    {
        $this->fromStatus = $fromStatus;
        return $this;
    }

    public function getToStatus(): string
    {
        return $this->toStatus;
    }

    public function setToStatus(string $toStatus): static
    {
        $this->toStatus = $toStatus;
        return $this;
    }

    public function getKomentarz(): ?string
    {
        return $this->komentarz;
    }

    public function setKomentarz(?string $komentarz): static
    {
        $this->komentarz = $komentarz;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
