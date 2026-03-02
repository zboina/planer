<?php

namespace Planer\PlanerBundle\Repository;

use Planer\PlanerBundle\Entity\Departament;
use Planer\PlanerBundle\Entity\PodanieUrlopowe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PodanieUrlopowe>
 */
class PodanieUrlopoweRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PodanieUrlopowe::class);
    }

    /**
     * Returns podania overlapping with a given month.
     * Result: [userId => [podanieId, dataOd, dataDo], ...]
     * A user may have multiple podania — we return all.
     *
     * @return PodanieUrlopowe[]
     */
    public function findForDepartamentAndMonth(Departament $dept, int $rok, int $miesiac): array
    {
        $monthStart = new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac));
        $monthEnd = (clone $monthStart)->modify('last day of this month');

        return $this->createQueryBuilder('p')
            ->where('p.departament = :dept')
            ->andWhere('p.dataOd <= :monthEnd')
            ->andWhere('p.dataDo >= :monthStart')
            ->setParameter('dept', $dept)
            ->setParameter('monthStart', $monthStart)
            ->setParameter('monthEnd', $monthEnd)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PodanieUrlopowe[]
     */
    public function findByUser(object $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Departament[] $departamenty
     * @return PodanieUrlopowe[]
     */
    public function findByDepartamenty(array $departamenty): array
    {
        if (empty($departamenty)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->where('p.departament IN (:depts)')
            ->setParameter('depts', $departamenty)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Departament[]|null $departamenty
     * @return PodanieUrlopowe[]
     */
    public function findDoAkceptacji(string $status, ?array $departamenty = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC');

        if ($departamenty !== null) {
            if (empty($departamenty)) {
                return [];
            }
            $qb->andWhere('p.departament IN (:depts)')
                ->setParameter('depts', $departamenty);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all podania with active statuses overlapping a given month (all departments).
     *
     * @param string[] $activeStatuses
     * @return PodanieUrlopowe[]
     */
    public function findActiveForMonth(int $rok, int $miesiac, array $activeStatuses): array
    {
        $monthStart = new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac));
        $monthEnd = (clone $monthStart)->modify('last day of this month');

        return $this->createQueryBuilder('p')
            ->where('p.dataOd <= :monthEnd')
            ->andWhere('p.dataDo >= :monthStart')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('monthStart', $monthStart)
            ->setParameter('monthEnd', $monthEnd)
            ->setParameter('statuses', $activeStatuses)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return string[]
     */
    public function findDistinctStatuses(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.status')
            ->getQuery()
            ->getSingleColumnResult();

        return $rows;
    }

    /**
     * @param string[] $excludeStatuses
     * @return PodanieUrlopowe[]
     */
    public function findAllExcludingStatuses(array $excludeStatuses): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC');

        if (!empty($excludeStatuses)) {
            $qb->andWhere('p.status NOT IN (:excluded)')
                ->setParameter('excluded', $excludeStatuses);
        }

        return $qb->getQuery()->getResult();
    }
}
