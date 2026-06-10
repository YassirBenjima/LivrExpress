<?php

namespace App\Repository;

use App\Entity\BonLivraison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BonLivraison>
 */
final class BonLivraisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BonLivraison::class);
    }

    /**
     * @return list<BonLivraison>
     */
    public function findAllForList(string $search = '', string $status = ''): array
    {
        $qb = $this->createQueryBuilder('bl')
            ->distinct()
            ->leftJoin('bl.colis', 'c')
            ->addSelect('c')
            ->orderBy('bl.createdAt', 'DESC');

        if ($status !== '') {
            $qb->andWhere('bl.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== '') {
            $qb->andWhere(
                'LOWER(bl.reference) LIKE :search OR LOWER(c.trackingCode) LIKE :search'
            )->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<int> $colisIds
     *
     * @return list<int> colis ids already assigned to another bon
     */
    public function findColisIdsAlreadyAssigned(array $colisIds, ?int $excludeBonId = null): array
    {
        $colisIds = array_values(array_filter(array_map('intval', $colisIds), static fn (int $v): bool => $v > 0));
        if ($colisIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('bl')
            ->select('c.id')
            ->innerJoin('bl.colis', 'c')
            ->andWhere('c.id IN (:ids)')
            ->andWhere('bl.status != :cancelled')
            ->setParameter('ids', $colisIds)
            ->setParameter('cancelled', BonLivraison::STATUS_ANNULE);

        if ($excludeBonId !== null && $excludeBonId > 0) {
            $qb->andWhere('bl.id != :excludeId')
                ->setParameter('excludeId', $excludeBonId);
        }

        $rows = $qb->getQuery()->getScalarResult();

        return array_values(array_unique(array_map(static fn (array $row): int => (int) $row['id'], $rows)));
    }
}
