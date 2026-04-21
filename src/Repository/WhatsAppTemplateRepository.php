<?php

namespace App\Repository;

use App\Entity\WhatsAppTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WhatsAppTemplate>
 */
final class WhatsAppTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WhatsAppTemplate::class);
    }

    /**
     * @return list<WhatsAppTemplate>
     */
    public function findForIndex(string $search = '', string $status = ''): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC');

        $search = trim($search);
        if ($search !== '') {
            $qb
                ->andWhere('LOWER(t.title) LIKE :q OR LOWER(t.message) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        $status = trim($status);
        if ($status !== '') {
            if ($status === 'default') {
                $qb
                    ->andWhere('t.isDefault = :isDefault')
                    ->setParameter('isDefault', true);
            } else {
                $qb
                    ->andWhere('t.status = :status')
                    ->setParameter('status', $status);
            }
        }

        /** @var list<WhatsAppTemplate> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }
}
