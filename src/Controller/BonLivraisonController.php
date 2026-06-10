<?php

namespace App\Controller;

use App\Entity\BonLivraison;
use App\Entity\Colis;
use App\Entity\User;
use App\Repository\BonLivraisonRepository;
use App\Repository\ColisRepository;
use App\Service\BonLivraisonPdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/bon-livraison')]
final class BonLivraisonController extends AbstractController
{
    #[Route('', name: 'app_bon_livraison_index', methods: ['GET'])]
    public function index(Request $request, BonLivraisonRepository $bonLivraisonRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $selectedStatut = trim((string) $request->query->get('statut', ''));

        $bons = $bonLivraisonRepository->findAllForList($search, $selectedStatut);

        return $this->render('bon_livraison/index.html.twig', [
            'bons' => $bons,
            'statuts_possibles' => BonLivraison::getStatusesPossibles(),
            'statut_labels' => BonLivraison::getStatusLabels(),
            'search_query' => $search,
            'selected_statut' => $selectedStatut,
        ]);
    }

    #[Route('/new', name: 'app_bon_livraison_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ColisRepository $colisRepository,
        BonLivraisonRepository $bonLivraisonRepository,
    ): Response {
        if ($request->isMethod('POST')) {
            return $this->handleFormSubmit($request, $entityManager, $colisRepository, $bonLivraisonRepository, null);
        }

        return $this->renderForm($request, $colisRepository, $bonLivraisonRepository, null);
    }

    #[Route('/{id}/edit', name: 'app_bon_livraison_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        BonLivraison $bon,
        EntityManagerInterface $entityManager,
        ColisRepository $colisRepository,
        BonLivraisonRepository $bonLivraisonRepository,
    ): Response {
        if ($request->isMethod('POST')) {
            return $this->handleFormSubmit($request, $entityManager, $colisRepository, $bonLivraisonRepository, $bon);
        }

        return $this->renderForm($request, $colisRepository, $bonLivraisonRepository, $bon);
    }

    #[Route('/{id}/download', name: 'app_bon_livraison_download', methods: ['GET'])]
    public function download(BonLivraison $bon, BonLivraisonPdfGenerator $pdfGenerator): Response
    {
        if (trim((string) $bon->getReference()) === '') {
            $this->addFlash('error', 'Document non disponible');

            return $this->redirectToRoute('app_bon_livraison_index');
        }

        try {
            return $pdfGenerator->generateDownloadResponse($bon);
        } catch (\Throwable) {
            $this->addFlash('error', 'Document non disponible');

            return $this->redirectToRoute('app_bon_livraison_index');
        }
    }

    #[Route('/{id}/delete', name: 'app_bon_livraison_delete', methods: ['POST'])]
    public function delete(Request $request, BonLivraison $bon, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_bon_livraison_' . $bon->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_bon_livraison_index');
        }

        $entityManager->remove($bon);
        $entityManager->flush();

        $this->addFlash('success', 'Bon de livraison supprimé avec succès.');

        return $this->redirectToRoute('app_bon_livraison_index');
    }

    private function renderForm(
        Request $request,
        ColisRepository $colisRepository,
        BonLivraisonRepository $bonLivraisonRepository,
        ?BonLivraison $bon,
    ): Response {
        $search = trim((string) $request->query->get('q', ''));
        $selectedColisIds = $bon !== null
            ? array_map(static fn(Colis $c): int => (int) $c->getId(), $bon->getColis()->toArray())
            : [];

        $availableColis = $this->filterAvailableColis($colisRepository->findBy([], ['id' => 'DESC']), $search, $bon, $bonLivraisonRepository);

        return $this->render('bon_livraison/new.html.twig', [
            'bon' => $bon,
            'is_edit_mode' => $bon !== null,
            'available_colis' => $availableColis,
            'selected_colis_ids' => $selectedColisIds,
            'search_query' => $search,
        ]);
    }

    private function handleFormSubmit(
        Request $request,
        EntityManagerInterface $entityManager,
        ColisRepository $colisRepository,
        BonLivraisonRepository $bonLivraisonRepository,
        ?BonLivraison $bon,
    ): Response {
        $tokenId = $bon !== null ? 'bon_livraison_edit_' . $bon->getId() : 'bon_livraison_new';
        $redirectRoute = $bon !== null ? 'app_bon_livraison_edit' : 'app_bon_livraison_new';
        $redirectParams = $bon !== null ? ['id' => $bon->getId()] : [];

        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute($redirectRoute, $redirectParams);
        }

        $colisIds = array_values(array_filter(
            array_map('intval', $request->request->all('colis_ids')),
            static fn(int $id): bool => $id > 0
        ));

        if ($colisIds === []) {
            $this->addFlash('error', 'Veuillez sélectionner au moins un colis.');

            return $this->redirectToRoute($redirectRoute, $redirectParams);
        }

        $alreadyAssigned = $bonLivraisonRepository->findColisIdsAlreadyAssigned(
            $colisIds,
            $bon?->getId()
        );
        if ($alreadyAssigned !== []) {
            $this->addFlash('error', 'Un ou plusieurs colis sont déjà associés à un autre bon de livraison.');

            return $this->redirectToRoute($redirectRoute, $redirectParams);
        }

        $colisList = $colisRepository->findBy(['id' => $colisIds]);
        if (\count($colisList) !== \count($colisIds)) {
            $this->addFlash('error', 'Un ou plusieurs colis sélectionnés sont introuvables.');

            return $this->redirectToRoute($redirectRoute, $redirectParams);
        }

        $isNew = $bon === null;
        if ($isNew) {
            $bon = new BonLivraison();
            $bon->generateReference();

            $user = $this->getUser();
            if ($user instanceof User) {
                $bon->setCreatedBy($user);
            }
        } else {
            $bon->clearColis();
        }

        foreach ($colisList as $colis) {
            $bon->addColis($colis);
        }

        $bon->setStatus(BonLivraison::STATUS_ENREGISTRE);
        $bon->setRegisteredAt(new \DateTimeImmutable());

        $entityManager->persist($bon);
        $entityManager->flush();

        $this->addFlash(
            'success',
            $isNew ? 'Bon de livraison créé avec succès.' : 'Bon de livraison mis à jour avec succès.'
        );

        return $this->redirectToRoute('app_bon_livraison_index');
    }

    /**
     * @param list<Colis> $allColis
     *
     * @return list<Colis>
     */
    private function filterAvailableColis(
        array $allColis,
        string $search,
        ?BonLivraison $currentBon,
        BonLivraisonRepository $bonLivraisonRepository,
    ): array {
        $assignedIds = $bonLivraisonRepository->findColisIdsAlreadyAssigned(
            array_map(static fn(Colis $c): int => (int) $c->getId(), $allColis),
            $currentBon?->getId()
        );
        $assignedMap = array_fill_keys($assignedIds, true);

        $currentSelectedIds = [];
        if ($currentBon !== null) {
            foreach ($currentBon->getColis() as $colis) {
                $currentSelectedIds[(int) $colis->getId()] = true;
            }
        }

        $filtered = [];
        foreach ($allColis as $colis) {
            $id = (int) $colis->getId();
            if (isset($assignedMap[$id]) && !isset($currentSelectedIds[$id])) {
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
