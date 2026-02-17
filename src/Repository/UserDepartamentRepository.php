<?php

namespace Planer\PlanerBundle\Repository;

use Planer\PlanerBundle\Entity\Departament;
use Planer\PlanerBundle\Entity\UserDepartament;
use Planer\PlanerBundle\Model\PlanerUserInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDepartament>
 */
class UserDepartamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDepartament::class);
    }

    /**
     * @return UserDepartament[]
     */
    public function findUsersForDepartament(Departament $departament, bool $includeHidden = true): array
    {
        $qb = $this->createQueryBuilder('ud')
            ->select('ud', 'u')
            ->join('ud.user', 'u')
            ->where('ud.departament = :dept')
            ->setParameter('dept', $departament);

        if (!$includeHidden) {
            $qb->andWhere('ud.czyUkryty = false');
        }

        return $qb
            ->orderBy('ud.czyGlowny', 'DESC')
            ->addOrderBy('ud.kolejnosc', 'ASC')
            ->addOrderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array<int, UserDepartament>> [userId => [departamentId => UserDepartament]]
     */
    public function findAllGroupedByUser(): array
    {
        $all = $this->createQueryBuilder('ud')
            ->select('ud', 'u', 'd')
            ->join('ud.user', 'u')
            ->join('ud.departament', 'd')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($all as $ud) {
            $grouped[$ud->getUser()->getId()][$ud->getDepartament()->getId()] = $ud;
        }

        return $grouped;
    }

    /**
     * @return PlanerUserInterface[]
     */
    public function findAllPlanerUsers(): array
    {
        $meta = $this->getEntityManager()->getClassMetadata(UserDepartament::class);
        $userClass = $meta->getAssociationTargetClass('user');

        return $this->getEntityManager()->getRepository($userClass)
            ->createQueryBuilder('u')
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countDistinctUsers(): int
    {
        return (int) $this->createQueryBuilder('ud')
            ->select('COUNT(DISTINCT ud.user)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
