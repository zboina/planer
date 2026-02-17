<?php

namespace Planer\PlanerBundle\Repository;

use Planer\PlanerBundle\Entity\PlanerUstawienia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanerUstawienia>
 */
class PlanerUstawieniaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanerUstawienia::class);
    }

    public function getSettings(): PlanerUstawienia
    {
        $settings = $this->find(1);

        if (!$settings) {
            $settings = new PlanerUstawienia();
            $this->getEntityManager()->persist($settings);
            $this->getEntityManager()->flush();
        }

        return $settings;
    }
}
