<?php

namespace Planer\PlanerBundle\Repository;

use Planer\PlanerBundle\Entity\Departament;
use Planer\PlanerBundle\Entity\GrafikWpis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GrafikWpis>
 */
class GrafikWpisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GrafikWpis::class);
    }

    /**
     * @return array<int, array<int, GrafikWpis>> Indexed by [userId][dayOfMonth]
     */
    public function findForDepartamentAndMonth(Departament $departament, int $rok, int $miesiac): array
    {
        $from = new \DateTime(sprintf('%04d-%02d-01', $rok, $miesiac));
        $to = (clone $from)->modify('last day of this month');

        $wpisy = $this->createQueryBuilder('gw')
            ->select('gw', 'tz')
            ->join('gw.typZmiany', 'tz')
            ->where('gw.departament = :dept')
            ->andWhere('gw.data BETWEEN :from AND :to')
            ->setParameter('dept', $departament)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $grid = [];
        foreach ($wpisy as $wpis) {
            $userId = $wpis->getUser()->getId();
            $day = (int) $wpis->getData()->format('j');
            $grid[$userId][$day] = $wpis;
        }

        return $grid;
    }

    /**
     * @return array<int, array<int, list<int>>> [userId => [month(1-12) => [day, day, ...]]]
     */
    public function findVacationForDepartamentAndYear(Departament $dept, int $rok, string $typSkrot = 'U'): array
    {
        $from = new \DateTime(sprintf('%04d-01-01', $rok));
        $to = new \DateTime(sprintf('%04d-12-31', $rok));

        $wpisy = $this->createQueryBuilder('gw')
            ->select('gw', 'tz')
            ->join('gw.typZmiany', 'tz')
            ->where('gw.departament = :dept')
            ->andWhere('gw.data BETWEEN :from AND :to')
            ->andWhere('tz.skrot = :skrot')
            ->setParameter('dept', $dept)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('skrot', $typSkrot)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($wpisy as $wpis) {
            $userId = $wpis->getUser()->getId();
            $month = (int) $wpis->getData()->format('n');
            $day = (int) $wpis->getData()->format('j');
            $result[$userId][$month][] = $day;
        }

        // Sort days within each month
        foreach ($result as $userId => $months) {
            foreach ($months as $month => $days) {
                sort($result[$userId][$month]);
            }
        }

        return $result;
    }

    public function findOneByUserDataDepartament(object $user, \DateTimeInterface $data, Departament $departament): ?GrafikWpis
    {
        return $this->createQueryBuilder('gw')
            ->where('gw.user = :user')
            ->andWhere('gw.data = :data')
            ->andWhere('gw.departament = :dept')
            ->setParameter('user', $user)
            ->setParameter('data', $data)
            ->setParameter('dept', $departament)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
