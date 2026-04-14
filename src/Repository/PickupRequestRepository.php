<?php

namespace App\Repository;

use App\Entity\PickupRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PickupRequest>
 */
final class PickupRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PickupRequest::class);
    }

    /**
     * @param list<int> $productIds
     *
     * @return list<int> product ids having at least one pending request
     */
    public function findProductIdsWithPendingRequests(array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds), static fn (int $v): bool => $v > 0));
        if ($productIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('pr')
            ->select('IDENTITY(pr.product) AS product_id')
            ->andWhere('pr.status = :status')
            ->andWhere('pr.product IN (:ids)')
            ->setParameter('status', 'pending')
            ->setParameter('ids', $productIds)
            ->groupBy('pr.product')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }

    public function hasPendingForProductId(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $count = (int) $this->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->andWhere('pr.status = :status')
            ->andWhere('pr.product = :productId')
            ->setParameter('status', 'pending')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}

