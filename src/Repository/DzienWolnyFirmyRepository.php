<?php

namespace Planer\PlanerBundle\Repository;

use Planer\PlanerBundle\Entity\DzienWolnyFirmy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DzienWolnyFirmy>
 */
class DzienWolnyFirmyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DzienWolnyFirmy::class);
    }

    /**
     * @return array<string, string> 'Y-m-d' => nazwa
     */
    public function findMapForYear(int $rok): array
    {
        $wpisy = $this->createQueryBuilder('d')
            ->where('d.data BETWEEN :from AND :to')
            ->setParameter('from', new \DateTime("$rok-01-01"))
            ->setParameter('to', new \DateTime("$rok-12-31"))
            ->orderBy('d.data', 'ASC')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($wpisy as $w) {
            $map[$w->getData()->format('Y-m-d')] = $w->getNazwa();
        }

        return $map;
    }
}
