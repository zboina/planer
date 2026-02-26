<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Planer\PlanerBundle\Entity\PlanerRola;

/**
 * @extends ServiceEntityRepository<PlanerRola>
 */
class PlanerRolaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanerRola::class);
    }

    public function hasRole(object $user, string $rola): bool
    {
        return (bool) $this->findOneBy(['user' => $user, 'rola' => $rola]);
    }

    /**
     * @return string[]
     */
    public function getRoles(object $user): array
    {
        $results = $this->findBy(['user' => $user]);
        return array_map(fn(PlanerRola $r) => $r->getRola(), $results);
    }

    /**
     * @return PlanerRola[]
     */
    public function findByRola(string $rola): array
    {
        return $this->findBy(['rola' => $rola]);
    }
}
