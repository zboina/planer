<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Planer\PlanerBundle\Entity\WorkflowKrok;

/**
 * @extends ServiceEntityRepository<WorkflowKrok>
 */
class WorkflowKrokRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkflowKrok::class);
    }

    /**
     * @return WorkflowKrok[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['kolejnosc' => 'ASC']);
    }
}
