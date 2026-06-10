<?php

namespace App\EventSubscriber;

use App\Entity\Colis;
use App\Service\CrbtManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
final class ColisCrbtSubscriber
{
    /** @var list<Colis> */
    private array $pendingColis = [];

    private bool $isFlushing = false;

    public function __construct(
        private readonly CrbtManager $crbtManager,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->queueIfEligible($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->queueIfEligible($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->isFlushing || $this->pendingColis === []) {
            return;
        }

        $pending = $this->pendingColis;
        $this->pendingColis = [];
        $em = $args->getObjectManager();

        $this->isFlushing = true;
        foreach ($pending as $colis) {
            $this->crbtManager->syncForColis($colis);
        }
        $em->flush();
        $this->isFlushing = false;
    }

    private function queueIfEligible(object $entity): void
    {
        if (!$entity instanceof Colis || !$entity->isCodPayment()) {
            return;
        }

        $this->pendingColis[$entity->getId() ?? spl_object_id($entity)] = $entity;
    }
}
