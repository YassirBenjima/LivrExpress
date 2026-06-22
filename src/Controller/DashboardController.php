<?php

namespace App\Controller;

use App\Entity\Colis;
use App\Repository\BonLivraisonRepository;
use App\Repository\ColisRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(ColisRepository $colisRepo, BonLivraisonRepository $bonLivraisonRepo): Response
    {
        $totalColis = $colisRepo->count([]);
        $colisLivres = $colisRepo->count(['etat' => Colis::ETAT_LIVRE]);
        $colisEnPreparation = $colisRepo->count(['etat' => Colis::ETAT_EN_PREPARATION]);
        $colisExpedies = $colisRepo->count(['etat' => Colis::ETAT_EXPEDIE]);
        $colisRetournes = $colisRepo->count(['etat' => Colis::ETAT_RETOUR]);
        $colisCrees = $colisRepo->count(['etat' => Colis::ETAT_CREE]);

        // CRBT totals
        $totalCrbt = $colisRepo->createQueryBuilder('c')
            ->select('SUM(c.price)')
            ->getQuery()
            ->getSingleScalarResult() ?: 0.0;

        $crbtLivres = $colisRepo->createQueryBuilder('c')
            ->select('SUM(c.price)')
            ->where('c.etat = :etatLivre')
            ->setParameter('etatLivre', Colis::ETAT_LIVRE)
            ->getQuery()
            ->getSingleScalarResult() ?: 0.0;

        $crbtEnCours = $colisRepo->createQueryBuilder('c')
            ->select('SUM(c.price)')
            ->where('c.etat NOT IN (:excludedEtats)')
            ->setParameter('excludedEtats', [Colis::ETAT_LIVRE, Colis::ETAT_RETOUR])
            ->getQuery()
            ->getSingleScalarResult() ?: 0.0;

        // Recent items
        $recentColis = $colisRepo->findBy([], ['createdAt' => 'DESC'], 5);
        $recentBons = $bonLivraisonRepo->findBy([], ['createdAt' => 'DESC'], 5);

        // Get volume statistics for the last 7 days
        $sevenDaysAgo = (new \DateTimeImmutable('-7 days'))->setTime(0, 0, 0);
        $volumeStats = $colisRepo->createQueryBuilder('c')
            ->select("SUBSTRING(c.createdAt, 1, 10) as dateStr, COUNT(c.id) as cnt")
            ->where('c.createdAt >= :startDate')
            ->setParameter('startDate', $sevenDaysAgo)
            ->groupBy('dateStr')
            ->orderBy('dateStr', 'ASC')
            ->getQuery()
            ->getResult();

        $chartLabels = [];
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $dt = (new \DateTimeImmutable("-$i days"));
            $date = $dt->format('Y-m-d');
            $dateLabel = $dt->format('d M');
            $chartLabels[] = $dateLabel;
            
            $found = false;
            foreach ($volumeStats as $stat) {
                if ($stat['dateStr'] === $date) {
                    $chartData[] = (int) $stat['cnt'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $chartData[] = 0;
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'totalColis' => $totalColis,
            'colisLivres' => $colisLivres,
            'colisEnPreparation' => $colisEnPreparation,
            'colisExpedies' => $colisExpedies,
            'colisRetournes' => $colisRetournes,
            'colisCrees' => $colisCrees,
            'totalCrbt' => (float) $totalCrbt,
            'crbtLivres' => (float) $crbtLivres,
            'crbtEnCours' => (float) $crbtEnCours,
            'recentColis' => $recentColis,
            'recentBons' => $recentBons,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
        ]);
    }
}
