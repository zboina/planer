<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Entity;

use Planer\PlanerBundle\Repository\WorkflowKrokRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkflowKrokRepository::class)]
#[ORM\Table(name: 'planer_workflow_krok')]
class WorkflowKrok
{
    public const TYPE_DEPARTMENT = 'department';
    public const TYPE_GLOBAL = 'global';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $key;

    #[ORM\Column(length: 100)]
    private string $label;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_GLOBAL;

    #[ORM\Column]
    private int $kolejnosc = 0;

    #[ORM\Column]
    private bool $pomijalne = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function isPomijalne(): bool
    {
        return $this->pomijalne;
    }

    public function setPomijalne(bool $pomijalne): static
    {
        $this->pomijalne = $pomijalne;
        return $this;
    }

    public function isDepartment(): bool
    {
        return $this->type === self::TYPE_DEPARTMENT;
    }

    public function isGlobal(): bool
    {
        return $this->type === self::TYPE_GLOBAL;
    }
}
