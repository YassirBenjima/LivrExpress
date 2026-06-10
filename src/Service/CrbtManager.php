<?php

namespace App\Service;

use App\Entity\Colis;
use App\Entity\Crbt;
use App\Repository\CrbtRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CrbtManager
{
    private const BASE_DELIVERY_FEE = 25.0;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CrbtRepository $crbtRepository,
    ) {}

    public function syncForColis(Colis $colis): ?Crbt
    {
        if (!$colis->isCodPayment()) {
            return null;
        }

        $crbt = $this->crbtRepository->findOneByColis($colis);
        $isNew = $crbt === null;

        if ($isNew) {
            $crbt = new Crbt();
            $crbt->setColis($colis);
            $crbt->setCreatedAt($colis->getCreatedAt() ?? new \DateTimeImmutable());
            $crbt->generateReference();
            $this->entityManager->persist($crbt);
        }

        $amounts = $this->calculateAmounts($colis);
        $crbt->setFrais($this->formatAmount($amounts['frais']));
        $crbt->setMontantFrais($this->formatAmount($amounts['montant_frais']));
        $crbt->setMontant($this->formatAmount($amounts['montant']));
        $crbt->setBalance($this->formatAmount($amounts['balance']));
        $crbt->setStatus($this->resolveStatus($colis, $crbt));

        if ($crbt->getStatus() === Crbt::STATUS_PAYE && $crbt->getPaidAt() === null) {
            $crbt->setPaidAt(new \DateTimeImmutable());
        }

        return $crbt;
    }

    public function syncMissingEntries(): int
    {
        $count = 0;
        foreach ($this->crbtRepository->findColisEligibleWithoutCrbt() as $colis) {
            if ($this->syncForColis($colis) !== null) {
                ++$count;
            }
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    /**
     * @return array{frais: float, montant_frais: float, montant: float, balance: float}
     */
    public function calculateAmounts(Colis $colis): array
    {
        $frais = $colis->getDeliveryFee() !== null
            ? (float) $colis->getDeliveryFee()
            : self::BASE_DELIVERY_FEE;

        $extras = $this->calculateExtraFees($colis);
        $montantFrais = $frais + $extras;
        $montant = max(0.0, (float) ($colis->getPrice() ?? 0));
        $balance = max(0.0, $montant - $montantFrais);

        return [
            'frais' => round($frais, 2),
            'montant_frais' => round($montantFrais, 2),
            'montant' => round($montant, 2),
            'balance' => round($balance, 2),
        ];
    }

    private function calculateExtraFees(Colis $colis): float
    {
        $extras = 0.0;

        $cartonFees = ['s' => 1.5, 'm' => 2.5, 'l' => 3.0];
        $carton = strtolower((string) ($colis->getCartonOption() ?? ''));
        if (isset($cartonFees[$carton])) {
            $extras += $cartonFees[$carton];
        }

        if ($colis->isFragile() || $colis->isAllFragile()) {
            $extras += 5.0;
        }

        if ($colis->getPackageOption() === 'Ouvrir le colis') {
            $extras += 0.0;
        }

        return $extras;
    }

    private function resolveStatus(Colis $colis, Crbt $crbt): string
    {
        if ($crbt->getStatus() === Crbt::STATUS_PAYE || $crbt->getPaidAt() !== null) {
            return Crbt::STATUS_PAYE;
        }

        if (Colis::normalizeEtatLabel($colis->getEtat()) === Colis::ETAT_LIVRE) {
            return Crbt::STATUS_DISPONIBLE;
        }

        return Crbt::STATUS_EN_ATTENTE;
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
