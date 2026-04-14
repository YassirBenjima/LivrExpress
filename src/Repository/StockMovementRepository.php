<?php

namespace App\Repository;

use App\Entity\StockMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockMovement>
 */
final class StockMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovement::class);
    }

    /**
     * @return list<StockMovement>
     */
    public function findEntryMovementsForIndex(string $search = ''): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.items', 'i')
            ->addSelect('i')
            ->leftJoin('i.variant', 'v')
            ->addSelect('v')
            ->leftJoin('v.product', 'p')
            ->addSelect('p')
            ->andWhere('m.direction = :dir')
            ->setParameter('dir', StockMovement::DIRECTION_ENTRY)
            ->orderBy('m.id', 'DESC');

        $search = trim($search);
        if ($search !== '') {
            $qb
                ->andWhere('LOWER(m.reference) LIKE :q OR LOWER(p.name) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        /** @var list<StockMovement> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    public function existsReference(string $reference): bool
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.reference = :ref')
            ->setParameter('ref', $reference)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}

