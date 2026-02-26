<?php

namespace Planer\PlanerBundle\Repository;

use Planer\PlanerBundle\Entity\PlanerModul;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanerModul>
 */
class PlanerModulRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanerModul::class);
    }

    public function findByKod(string $kod): ?PlanerModul
    {
        return $this->findOneBy(['kod' => $kod]);
    }

    /**
     * @return PlanerModul[]
     */
    public function findAllActive(): array
    {
        return $this->findBy(['aktywny' => true], ['kolejnosc' => 'ASC']);
    }

    /**
     * @return PlanerModul[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['kolejnosc' => 'ASC', 'nazwa' => 'ASC']);
    }
}
