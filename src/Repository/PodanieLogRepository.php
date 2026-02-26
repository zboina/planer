<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Planer\PlanerBundle\Entity\PodanieLog;

/**
 * @extends ServiceEntityRepository<PodanieLog>
 */
class PodanieLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PodanieLog::class);
    }

    /**
     * @return PodanieLog[]
     */
    public function findByPodanie(object $podanie): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.podanie = :podanie')
            ->setParameter('podanie', $podanie)
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PodanieLog[]
     */
    public function findByPodanieIds(array $podanieIds): array
    {
        if (empty($podanieIds)) {
            return [];
        }

        return $this->createQueryBuilder('l')
            ->andWhere('l.podanie IN (:ids)')
            ->setParameter('ids', $podanieIds)
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
