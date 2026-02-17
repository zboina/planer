<?php

namespace Planer\PlanerBundle\Model;

interface PlanerUserInterface
{
    public function getId(): ?int;

    public function getFullName(): ?string;

    public function getIloscDniUrlopuWRoku(): int;

    public function setIloscDniUrlopuWRoku(int $iloscDniUrlopuWRoku): static;

    public function getAdres(): ?string;

    public function setAdres(?string $adres): static;
}
