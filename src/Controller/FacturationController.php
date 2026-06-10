<?php

namespace App\Controller;

use App\Entity\Crbt;
use App\Repository\CrbtRepository;
use App\Service\CrbtManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/facturation')]
final class FacturationController extends AbstractController
{
    #[Route('/crbt', name: 'app_facturation_crbt_index', methods: ['GET'])]
    public function crbtIndex(
        Request $request,
        CrbtRepository $crbtRepository,
        CrbtManager $crbtManager,
    ): Response {
        $crbtManager->syncMissingEntries();

        $search = trim((string) $request->query->get('q', ''));
        $selectedStatut = trim((string) $request->query->get('statut', ''));
        $dateFrom = $this->parseDate((string) $request->query->get('date_from', ''));
        $dateTo = $this->parseDate((string) $request->query->get('date_to', ''));

        $entries = $crbtRepository->findAllForList($search, $selectedStatut, $dateFrom, $dateTo);
        $summary = $crbtRepository->computeSummaryTotals($search, $selectedStatut, $dateFrom, $dateTo);

        return $this->render('facturation/crbt/index.html.twig', [
            'entries' => $entries,
            'statuts_possibles' => Crbt::getStatusesPossibles(),
            'statut_labels' => Crbt::getStatusLabels(),
            'search_query' => $search,
            'selected_statut' => $selectedStatut,
            'date_from' => $dateFrom?->format('Y-m-d') ?? '',
            'date_to' => $dateTo?->format('Y-m-d') ?? '',
            'summary' => $summary,
        ]);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }
}
