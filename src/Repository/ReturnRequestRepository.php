<?php

namespace App\Repository;

use App\Entity\ReturnRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReturnRequest>
 */
final class ReturnRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReturnRequest::class);
    }

    /**
     * @return list<ReturnRequest>
     */
    public function findAllForList(string $search = '', string $status = ''): array
    {
        $qb = $this->createQueryBuilder('rr')
            ->distinct()
            ->leftJoin('rr.colis', 'c')
            ->addSelect('c')
            ->orderBy('rr.createdAt', 'DESC');

        if ($status !== '') {
            $qb->andWhere('rr.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== '') {
            $qb->andWhere(
                'LOWER(rr.bonReference) LIKE :search
                OR LOWER(rr.receptionType) LIKE :search
                OR LOWER(rr.note) LIKE :search
                OR LOWER(c.trackingCode) LIKE :search'
            )->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<ReturnRequest>
     */
    public function findAllForClientList(int $userId, string $search = '', string $status = ''): array
    {
        $qb = $this->createQueryBuilder('rr')
            ->distinct()
            ->leftJoin('rr.colis', 'c')
            ->addSelect('c')
            ->andWhere('rr.bonReference IS NOT NULL')
            ->andWhere('rr.createdBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('rr.createdAt', 'DESC');

        if ($status !== '') {
            $qb->andWhere('rr.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== '') {
            $qb->andWhere(
                'LOWER(rr.bonReference) LIKE :search OR LOWER(c.trackingCode) LIKE :search'
            )->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<int> $colisIds
     *
     * @return list<int>
     */
    public function findColisIdsAlreadyAssigned(array $colisIds, ?int $excludeRequestId = null): array
    {
        $colisIds = array_values(array_filter(array_map('intval', $colisIds), static fn (int $v): bool => $v > 0));
        if ($colisIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('rr')
            ->select('c.id')
            ->innerJoin('rr.colis', 'c')
            ->andWhere('c.id IN (:ids)')
            ->andWhere('rr.status != :cancelled')
            ->setParameter('ids', $colisIds)
            ->setParameter('cancelled', ReturnRequest::STATUS_CANCELLED);

        if ($excludeRequestId !== null && $excludeRequestId > 0) {
            $qb->andWhere('rr.id != :excludeId')
                ->setParameter('excludeId', $excludeRequestId);
        }

        $rows = $qb->getQuery()->getScalarResult();

        return array_values(array_unique(array_map(static fn (array $row): int => (int) $row['id'], $rows)));
    }
}
