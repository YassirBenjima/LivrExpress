<?php

namespace App\Repository;

use App\Entity\Colis;
use App\Entity\Crbt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Crbt>
 */
final class CrbtRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Crbt::class);
    }

    /**
     * @return list<Crbt>
     */
    public function findAllForList(
        string $search = '',
        string $status = '',
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.colis', 'colis')
            ->addSelect('colis')
            ->orderBy('c.createdAt', 'DESC');

        if ($status !== '') {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        if ($dateFrom !== null) {
            $qb->andWhere('c.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom->setTime(0, 0));
        }

        if ($dateTo !== null) {
            $qb->andWhere('c.createdAt <= :dateTo')
                ->setParameter('dateTo', $dateTo->setTime(23, 59, 59));
        }

        if ($search !== '') {
            $qb->andWhere(
                'LOWER(c.reference) LIKE :search
                OR LOWER(colis.trackingCode) LIKE :search
                OR LOWER(colis.orderNumber) LIKE :search'
            )->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByColis(Colis $colis): ?Crbt
    {
        return $this->findOneBy(['colis' => $colis]);
    }

    /**
     * @return array{disponible: float, en_attente: float, paye: float}
     */
    public function computeSummaryTotals(
        string $search = '',
        string $status = '',
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
    ): array {
        $entries = $this->findAllForList($search, $status, $dateFrom, $dateTo);

        $totals = [
            'disponible' => 0.0,
            'en_attente' => 0.0,
            'paye' => 0.0,
        ];

        foreach ($entries as $entry) {
            $balance = (float) $entry->getBalance();
            $montant = (float) $entry->getMontant();

            match ($entry->getStatus()) {
                Crbt::STATUS_DISPONIBLE => $totals['disponible'] += $balance,
                Crbt::STATUS_EN_ATTENTE => $totals['en_attente'] += $balance,
                Crbt::STATUS_PAYE => $totals['paye'] += $montant,
                default => null,
            };
        }

        return $totals;
    }

    /**
     * @return list<Colis>
     */
    public function findColisEligibleWithoutCrbt(): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('colis')
            ->from(Colis::class, 'colis')
            ->leftJoin(Crbt::class, 'crbt', 'WITH', 'crbt.colis = colis')
            ->andWhere('crbt.id IS NULL')
            ->andWhere('colis.paymentType IN (:types)')
            ->setParameter('types', [Colis::PAYMENT_COD, Colis::PAYMENT_CRBT]);

        return $qb->getQuery()->getResult();
    }
}
