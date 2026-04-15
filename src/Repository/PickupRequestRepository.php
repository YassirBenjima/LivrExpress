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

    /**
     * @return list<PickupRequest>
     */
    public function findAllForList(string $search = '', string $selectedStatus = ''): array
    {
        $qb = $this->createQueryBuilder('pr')
            ->leftJoin('pr.createdBy', 'u')
            ->addSelect('u')
            ->orderBy('pr.id', 'DESC');

        if ($selectedStatus !== '') {
            $qb->andWhere('pr.status = :status')
               ->setParameter('status', $selectedStatus);
        }

        if ($search !== '') {
            $qb->andWhere(
                'pr.phone LIKE :search OR pr.city LIKE :search OR pr.address LIKE :search ' .
                'OR pr.productNameSnapshot LIKE :search OR pr.note LIKE :search OR pr.neighborhood LIKE :search'
            )->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{pending: int, confirmed: int, picked_up: int, cancelled: int, total: int}
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('pr')
            ->select('pr.status, COUNT(pr.id) as cnt')
            ->groupBy('pr.status')
            ->getQuery()
            ->getArrayResult();

        $result = ['pending' => 0, 'confirmed' => 0, 'picked_up' => 0, 'cancelled' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            $count = (int) ($row['cnt'] ?? 0);
            if (isset($result[$status])) {
                $result[$status] = $count;
            }
            $result['total'] += $count;
        }

        return $result;
    }

    /**
     * @return array{pending: list<PickupRequest>, confirmed: list<PickupRequest>, picked_up: list<PickupRequest>}
     */
    public function findGroupedByStatus(): array
    {
        $all = $this->createQueryBuilder('pr')
            ->leftJoin('pr.createdBy', 'u')
            ->addSelect('u')
            ->andWhere('pr.status != :cancelled')
            ->setParameter('cancelled', 'cancelled')
            ->orderBy('pr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $grouped = ['pending' => [], 'confirmed' => [], 'picked_up' => []];
        foreach ($all as $pickup) {
            if (!$pickup instanceof PickupRequest) {
                continue;
            }
            $status = $pickup->getStatus();
            if (isset($grouped[$status])) {
                $grouped[$status][] = $pickup;
            }
        }

        return $grouped;
    }
}

