<?php

namespace Planer\PlanerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'szablon_podania')]
class SzablonPodania
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nazwa = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $trescHtml = null;

    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $polaFormularza = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $aktywny = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getTrescHtml(): ?string
    {
        return $this->trescHtml;
    }

    public function setTrescHtml(string $trescHtml): static
    {
        $this->trescHtml = $trescHtml;
        return $this;
    }

    public function getPolaFormularza(): array
    {
        return $this->polaFormularza;
    }

    public function setPolaFormularza(array $polaFormularza): static
    {
        $this->polaFormularza = $polaFormularza;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nazwa ?? '';
    }
}
