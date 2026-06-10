<?php

namespace App\Controller;

use App\Entity\Colis;
use App\Entity\ReturnRequest;
use App\Entity\User;
use App\Repository\ColisRepository;
use App\Repository\ReturnRequestRepository;
use App\Service\BonRetourPdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/retour')]
final class RetourController extends AbstractController
{
    #[Route('/demandes', name: 'app_retour_demande_index', methods: ['GET'])]
    public function demandeIndex(Request $request, ReturnRequestRepository $returnRequestRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $selectedStatut = trim((string) $request->query->get('statut', ''));

        $demandes = $returnRequestRepository->findAllForList($search, $selectedStatut);

        return $this->render('retour/demandes/index.html.twig', [
            'demandes' => $demandes,
            'statuts_possibles' => ReturnRequest::getStatusesPossibles(),
            'statut_labels' => ReturnRequest::getStatusLabels(),
            'search_query' => $search,
            'selected_statut' => $selectedStatut,
        ]);
    }

    #[Route('/bons', name: 'app_retour_bons_index', methods: ['GET'])]
    public function bonsIndex(Request $request, ReturnRequestRepository $returnRequestRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $search = trim((string) $request->query->get('q', ''));
        $selectedStatut = trim((string) $request->query->get('statut', ''));

        $bons = $returnRequestRepository->findAllForClientList(
            (int) $user->getId(),
            $search,
            $selectedStatut,
        );

        return $this->render('retour/bons/index.html.twig', [
            'bons' => $bons,
            'statuts_possibles' => ReturnRequest::getStatusesPossibles(),
            'statut_labels' => ReturnRequest::getStatusLabels(),
            'search_query' => $search,
            'selected_statut' => $selectedStatut,
        ]);
    }

    #[Route('/bons/{id}/download', name: 'app_retour_bons_download', methods: ['GET'])]
    public function bonsDownload(ReturnRequest $demande, BonRetourPdfGenerator $pdfGenerator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $ownerId = $demande->getCreatedBy()?->getId();
        if ($ownerId === null || $ownerId !== $user->getId()) {
            $this->addFlash('error', 'Document non disponible');

            return $this->redirectToRoute('app_retour_bons_index');
        }

        if (trim((string) $demande->getBonReference()) === '') {
            $this->addFlash('error', 'Document non disponible');

            return $this->redirectToRoute('app_retour_bons_index');
        }

        try {
            return $pdfGenerator->generateDownloadResponse($demande);
        } catch (\Throwable) {
            $this->addFlash('error', 'Document non disponible');

            return $this->redirectToRoute('app_retour_bons_index');
        }
    }

    #[Route('/demandes/new', name: 'app_retour_demande_new', methods: ['GET', 'POST'])]
    public function demandeNew(
        Request $request,
        EntityManagerInterface $entityManager,
        ColisRepository $colisRepository,
        ReturnRequestRepository $returnRequestRepository,
    ): Response {
        if ($request->isMethod('POST')) {
            return $this->handleDemandeSubmit($request, $entityManager, $colisRepository, $returnRequestRepository);
        }

        $search = trim((string) $request->query->get('q', ''));
        $availableColis = $this->filterAvailableColis(
            $colisRepository->findBy([], ['id' => 'DESC']),
            $search,
            $returnRequestRepository,
        );

        $user = $this->getUser();
        $receptionType = $user instanceof User ? ($user->getReturnReception() ?? 'En Agence') : 'En Agence';

        return $this->render('retour/demandes/new.html.twig', [
            'available_colis' => $availableColis,
            'search_query' => $search,
            'reception_type' => $receptionType,
        ]);
    }

    private function handleDemandeSubmit(
        Request $request,
        EntityManagerInterface $entityManager,
        ColisRepository $colisRepository,
        ReturnRequestRepository $returnRequestRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('retour_demande_new', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_retour_demande_new');
        }

        $colisIds = array_values(array_filter(
            array_map('intval', $request->request->all('colis_ids')),
            static fn (int $id): bool => $id > 0
        ));

        if ($colisIds === []) {
            $this->addFlash('error', 'Veuillez sélectionner au moins un colis.');

            return $this->redirectToRoute('app_retour_demande_new');
        }

        $alreadyAssigned = $returnRequestRepository->findColisIdsAlreadyAssigned($colisIds);
        if ($alreadyAssigned !== []) {
            $this->addFlash('error', 'Un ou plusieurs colis sont déjà associés à une autre demande de retour.');

            return $this->redirectToRoute('app_retour_demande_new');
        }

        $colisList = $colisRepository->findBy(['id' => $colisIds]);
        if (\count($colisList) !== \count($colisIds)) {
            $this->addFlash('error', 'Un ou plusieurs colis sélectionnés sont introuvables.');

            return $this->redirectToRoute('app_retour_demande_new');
        }

        foreach ($colisList as $colis) {
            if ($colis->isRetourne()) {
                $this->addFlash('error', 'Les colis déjà retournés ne peuvent pas être ajoutés à une demande.');

                return $this->redirectToRoute('app_retour_demande_new');
            }
        }

        $user = $this->getUser();
        $receptionType = trim((string) $request->request->get('reception_type', ''));
        if ($receptionType === '' && $user instanceof User) {
            $receptionType = $user->getReturnReception() ?? 'En Agence';
        }
        if ($receptionType === '') {
            $receptionType = 'En Agence';
        }

        $demande = new ReturnRequest();
        $demande->setReceptionType($receptionType);
        $demande->setNote(trim((string) $request->request->get('note', '')) ?: null);
        $demande->setStatus(ReturnRequest::STATUS_PENDING);
        $demande->generateBonReference();

        if ($user instanceof User) {
            $demande->setCreatedBy($user);
        }

        foreach ($colisList as $colis) {
            $demande->addColis($colis);
        }

        $entityManager->persist($demande);
        $entityManager->flush();

        $this->addFlash('success', 'Demande de retour créée avec succès.');

        return $this->redirectToRoute('app_retour_demande_index');
    }

    /**
     * @param list<Colis> $allColis
     *
     * @return list<Colis>
     */
    private function filterAvailableColis(
        array $allColis,
        string $search,
        ReturnRequestRepository $returnRequestRepository,
    ): array {
        $assignedIds = $returnRequestRepository->findColisIdsAlreadyAssigned(
            array_map(static fn (Colis $c): int => (int) $c->getId(), $allColis),
        );
        $assignedMap = array_fill_keys($assignedIds, true);

        $filtered = [];
        foreach ($allColis as $colis) {
            $id = (int) $colis->getId();
            if (isset($assignedMap[$id]) || $colis->isRetourne()) {
                continue;
            }

            if ($search !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) $colis->getTrackingCode(),
                    (string) $colis->getOrderNumber(),
                    (string) $colis->getProductNature(),
                    (string) $colis->getCity(),
                    (string) $colis->getRecipient(),
                ]));
                if (!str_contains($haystack, mb_strtolower($search))) {
                    continue;
                }
            }

            $filtered[] = $colis;
        }

        return $filtered;
    }
}
