<?php

namespace App\Controller;

use App\Entity\Colis;
use App\Entity\User;
use App\Form\ColisType;
use App\Repository\CityRepository;
use App\Repository\ColisRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/colis')]
final class ColisController extends AbstractController
{
    #[Route('', name: 'app_colis_index', methods: ['GET'])]
    public function index(Request $request, ColisRepository $colisRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $selectedEtat = self::normalizeEtat(trim((string) $request->query->get('etat', '')));
        $selectedStatut = self::normalizeStatut(trim((string) $request->query->get('statut', '')));

        $colisList = $colisRepository->findBy([], ['id' => 'DESC']);
        $colisList = array_values(array_filter($colisList, static function (Colis $colis) use ($search, $selectedEtat, $selectedStatut): bool {
            $etat = self::normalizeEtat((string) ($colis->getEtat() ?? Colis::ETAT_CREE));
            $statut = self::normalizeStatut((string) ($colis->getStatut() ?? Colis::STATUT_EN_ATTENTE));

            // "Liste des colis" should only display packages requested for pickup.
            if ($statut === Colis::STATUT_EN_ATTENTE) {
                return false;
            }

            if ($selectedEtat !== '' && $etat !== $selectedEtat) {
                return false;
            }

            if ($selectedStatut !== '' && $statut !== $selectedStatut) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', [
                (string) $colis->getTrackingCode(),
                (string) $colis->getOrderNumber(),
                (string) $colis->getProductNature(),
                (string) $colis->getCity(),
                (string) $colis->getAddress(),
                (string) $colis->getRecipient(),
                $etat,
                $statut,
            ]));

            return str_contains($haystack, mb_strtolower($search));
        }));

        return $this->render('colis/index.html.twig', [
            'colis_list' => $colisList,
            'etats_possibles' => array_values(array_filter(
                Colis::getEtatsPossibles(),
                static fn (string $etat): bool => $etat !== Colis::ETAT_CREE
            )),
            'statuts_possibles' => array_values(array_filter(
                Colis::getStatutsPossibles(),
                static fn (string $statut): bool => $statut !== Colis::STATUT_EN_ATTENTE
            )),
            'search_query' => $search,
            'selected_etat' => $selectedEtat,
            'selected_statut' => $selectedStatut,
        ]);
    }

    private static function normalizeEtat(string $etat): string
    {
        return match ($etat) {
            'Cree' => Colis::ETAT_CREE,
            'En preparation' => Colis::ETAT_EN_PREPARATION,
            'Expedie' => Colis::ETAT_EXPEDIE,
            'Livre' => Colis::ETAT_LIVRE,
            'Retour' => Colis::ETAT_RETOUR,
            default => $etat,
        };
    }

    private static function normalizeStatut(string $statut): string
    {
        return match ($statut) {
            'Reporte' => Colis::STATUT_REPORTE,
            'Echec' => Colis::STATUT_ECHEC,
            'Termine' => Colis::STATUT_TERMINE,
            default => $statut,
        };
    }

    #[Route('/new', name: 'app_colis_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CityRepository $cityRepository, ColisRepository $colisRepository): Response
    {
        $colis = new Colis();
        $cityChoices = [];
        foreach ($cityRepository->findBy([], ['name' => 'ASC']) as $city) {
            $cityName = (string) $city->getName();
            $cityChoices[$cityName] = $cityName;
        }
        $oldColisChoices = [];
        foreach ($colisRepository->findBy([], ['id' => 'DESC']) as $oldColis) {
            $orderNumber = (string) $oldColis->getOrderNumber();
            $oldColisChoices[$orderNumber] = $orderNumber;
        }
        $user = $this->getUser();
        $defaultPackageOption = $user instanceof User ? $user->getPackageOption() : null;
        if (!$defaultPackageOption) {
            $defaultPackageOption = 'Ne pas ouvrir le colis';
        }

        $form = $this->createForm(ColisType::class, $colis, [
            'city_choices' => $cityChoices,
            'old_colis_choices' => $oldColisChoices,
            'default_package_option' => $defaultPackageOption,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $request->request->all((string) $form->getName());
            $submittedCartonOption = $form->get('cartonOption')->getData();
            $submittedOldColis = $submittedData['oldColis'] ?? null;
            $allowedCartonOptions = ['s', 'm', 'l'];
            if (!\is_string($submittedCartonOption) || !\in_array($submittedCartonOption, $allowedCartonOptions, true)) {
                $submittedCartonOption = null;
            }
            $colis->setCartonOption($submittedCartonOption);

            if (\is_string($submittedOldColis) && $submittedOldColis !== '') {
                $colis->setOldOrderNumber($submittedOldColis);
            }

            if (!$colis->isReplacePackage()) {
                $colis->setOldOrderNumber(null);
            }

            if (!$colis->getCartonOption()) {
                $colis->setCartonOption(null);
            }

            try {
                $entityManager->persist($colis);
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Numero de commande deja utilise. Veuillez saisir un numero unique.');

                return $this->redirectToRoute('app_colis_new');
            }

            $this->addFlash('success', 'Colis ajoute avec succes.');

            return $this->redirectToRoute('app_colis_pickup');
        }

        return $this->render('colis/new.html.twig', [
            'form' => $form->createView(),
            'is_edit_mode' => false,
        ]);
    }

    #[Route('/pickup', name: 'app_colis_pickup', methods: ['GET'])]
    public function pickup(Request $request, ColisRepository $colisRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $selectedEtat = self::normalizeEtat(trim((string) $request->query->get('etat', '')));
        $selectedStatut = self::normalizeStatut(trim((string) $request->query->get('statut', '')));

        $colisList = $colisRepository->findBy([], ['id' => 'DESC']);
        $colisList = array_values(array_filter($colisList, static function (Colis $colis) use ($search, $selectedEtat, $selectedStatut): bool {
            $etat = self::normalizeEtat((string) ($colis->getEtat() ?? Colis::ETAT_CREE));
            $statut = self::normalizeStatut((string) ($colis->getStatut() ?? Colis::STATUT_EN_ATTENTE));

            // "Colis pour ramassage": only waiting packages.
            if ($statut !== Colis::STATUT_EN_ATTENTE) {
                return false;
            }

            if ($selectedEtat !== '' && $etat !== $selectedEtat) {
                return false;
            }

            if ($selectedStatut !== '' && $statut !== $selectedStatut) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', [
                (string) $colis->getTrackingCode(),
                (string) $colis->getOrderNumber(),
                (string) $colis->getProductNature(),
                (string) $colis->getCity(),
                (string) $colis->getAddress(),
                (string) $colis->getRecipient(),
                $etat,
                $statut,
            ]));

            return str_contains($haystack, mb_strtolower($search));
        }));

        $pickupEtats = [];
        $pickupStatuts = [];
        foreach ($colisList as $colis) {
            $etat = self::normalizeEtat((string) ($colis->getEtat() ?? Colis::ETAT_CREE));
            $statut = self::normalizeStatut((string) ($colis->getStatut() ?? Colis::STATUT_EN_ATTENTE));
            $pickupEtats[$etat] = $etat;
            $pickupStatuts[$statut] = $statut;
        }

        return $this->render('colis/pickup.html.twig', [
            'colis_list' => $colisList,
            'etats_possibles' => array_values($pickupEtats),
            'statuts_possibles' => array_values($pickupStatuts),
            'search_query' => $search,
            'selected_etat' => $selectedEtat,
            'selected_statut' => $selectedStatut,
        ]);
    }

    #[Route('/{id}/request-pickup', name: 'app_colis_request_pickup', methods: ['POST'])]
    public function requestPickup(Request $request, Colis $colis, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('request_pickup_'.$colis->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_colis_pickup');
        }

        $colis->setStatut(Colis::STATUT_EN_COURS);
        $colis->setEtat(Colis::ETAT_EN_PREPARATION);
        $entityManager->flush();

        $this->addFlash('success', 'Demande de ramassage envoyee. Le colis est passe dans la liste des colis.');

        return $this->redirectToRoute('app_colis_pickup');
    }

    #[Route('/request-pickup/bulk', name: 'app_colis_request_pickup_bulk', methods: ['POST'])]
    public function requestPickupBulk(Request $request, ColisRepository $colisRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('request_pickup_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_colis_pickup');
        }

        $ids = $request->request->all('colis_ids');
        if (!\is_array($ids) || $ids === []) {
            $this->addFlash('error', 'Aucun colis selectionne.');

            return $this->redirectToRoute('app_colis_pickup');
        }

        $updated = 0;
        foreach ($ids as $id) {
            if (!\is_scalar($id)) {
                continue;
            }

            $colis = $colisRepository->find((int) $id);
            if (!$colis) {
                continue;
            }

            if ($colis->getStatut() !== Colis::STATUT_EN_ATTENTE) {
                continue;
            }

            $colis->setStatut(Colis::STATUT_EN_COURS);
            $colis->setEtat(Colis::ETAT_EN_PREPARATION);
            ++$updated;
        }

        if ($updated > 0) {
            $entityManager->flush();
            $this->addFlash('success', sprintf('%d colis envoye(s) en demande de ramassage.', $updated));
        } else {
            $this->addFlash('error', 'Aucun colis valide a traiter.');
        }

        return $this->redirectToRoute('app_colis_pickup');
    }

    #[Route('/{id}/edit', name: 'app_colis_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Colis $colis, EntityManagerInterface $entityManager, CityRepository $cityRepository, ColisRepository $colisRepository): Response
    {
        if ($colis->getStatut() !== Colis::STATUT_EN_ATTENTE) {
            $this->addFlash('error', 'Ce colis ne peut plus etre modifie car il est deja en cours de traitement.');

            return $this->redirectToRoute('app_colis_index');
        }

        $cityChoices = [];
        foreach ($cityRepository->findBy([], ['name' => 'ASC']) as $city) {
            $cityName = (string) $city->getName();
            $cityChoices[$cityName] = $cityName;
        }

        $oldColisChoices = [];
        foreach ($colisRepository->findBy([], ['id' => 'DESC']) as $oldColis) {
            $orderNumber = (string) $oldColis->getOrderNumber();
            $oldColisChoices[$orderNumber] = $orderNumber;
        }

        $form = $this->createForm(ColisType::class, $colis, [
            'city_choices' => $cityChoices,
            'old_colis_choices' => $oldColisChoices,
            'default_package_option' => $colis->getPackageOption() ?: 'Ne pas ouvrir le colis',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $request->request->all((string) $form->getName());
            $submittedCartonOption = $form->get('cartonOption')->getData();
            $submittedOldColis = $submittedData['oldColis'] ?? null;
            $allowedCartonOptions = ['s', 'm', 'l'];
            if (!\is_string($submittedCartonOption) || !\in_array($submittedCartonOption, $allowedCartonOptions, true)) {
                $submittedCartonOption = null;
            }
            $colis->setCartonOption($submittedCartonOption);

            if (\is_string($submittedOldColis) && $submittedOldColis !== '') {
                $colis->setOldOrderNumber($submittedOldColis);
            }

            if (!$colis->isReplacePackage()) {
                $colis->setOldOrderNumber(null);
            }

            if (!$colis->getCartonOption()) {
                $colis->setCartonOption(null);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Colis modifie avec succes.');

            return $this->redirectToRoute('app_colis_pickup');
        }

        return $this->render('colis/new.html.twig', [
            'form' => $form->createView(),
            'is_edit_mode' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_colis_delete', methods: ['POST'])]
    public function delete(Request $request, Colis $colis, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_colis_'.$colis->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_colis_pickup');
        }

        if ($colis->getStatut() !== Colis::STATUT_EN_ATTENTE) {
            $this->addFlash('error', 'Ce colis ne peut plus etre supprime car il est deja en cours de traitement.');

            return $this->redirectToRoute('app_colis_index');
        }

        $entityManager->remove($colis);
        $entityManager->flush();

        $this->addFlash('success', 'Colis supprime avec succes.');

        return $this->redirectToRoute('app_colis_pickup');
    }

    #[Route('/import', name: 'app_colis_import', methods: ['GET'])]
    public function import(): Response
    {
        return $this->render('colis/import.html.twig');
    }

    #[Route('/import-process', name: 'app_colis_import_process', methods: ['POST'])]
    public function importProcess(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('colis_import', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');

            return $this->json([
                'success' => false,
                'error' => 'Jeton CSRF invalide.',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$file) {
            $this->addFlash('error', 'Aucun fichier reçu.');

            return $this->json([
                'success' => false,
                'error' => 'Aucun fichier reçu.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $xlsx = \Shuchkin\SimpleXLSX::parse($file->getRealPath());
        if (!$xlsx) {
            $this->addFlash('error', 'Import échoué. Vérifiez le fichier et réessayez.');

            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la lecture du fichier Excel : ' . \Shuchkin\SimpleXLSX::parseError(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $rows = $xlsx->rows();
        if (\count($rows) < 2) {
            $this->addFlash('error', 'Le fichier est vide ou ne contient que des en-têtes.');

            return $this->json([
                'success' => false,
                'error' => 'Le fichier est vide ou ne contient que des en-têtes.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $headers = array_shift($rows);
        $importedCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $data = array_combine($headers, $row);
            
            // Minimal validation
            if (empty($data['N° Commande']) || empty($data['Ville']) || empty($data['Prix (DH)'])) {
                $errors[] = sprintf("Ligne %d : Champs obligatoires manquants (N° Commande, Ville, Prix).", $index + 2);
                continue;
            }

            $colis = new Colis();
            $colis->setOrderNumber((string) $data['N° Commande']);
            $colis->setRecipient($data['Destinataire'] ?? null);
            $colis->setPhoneNumber((string) ($data['Téléphone'] ?? ''));
            $colis->setCity($data['Ville']);
            $colis->setNeighborhood($data['Quartier'] ?? '');
            $colis->setAddress($data['Adresse'] ?? '');
            $colis->setPrice((string) $data['Prix (DH)']);
            $colis->setProductNature($data['Nature de Produit'] ?? 'Marchandise');
            
            // Map Type
            $typeInput = strtolower($data['Type'] ?? '');
            if (str_contains($typeInput, 'stock')) {
                $colis->setType(Colis::TYPE_STOCK);
            } else {
                $colis->setType(Colis::TYPE_SIMPLE);
            }

            $colis->setComment($data['Commentaire'] ?? null);
            $colis->setPackageOption($data['Option Colis'] ?? 'Ne pas ouvrir le colis');

            try {
                $entityManager->persist($colis);
                $importedCount++;
            } catch (\Exception $e) {
                $errors[] = sprintf("Ligne %d : Erreur lors de l'enregistrement (%s).", $index + 2, $e->getMessage());
            }
        }

        if ($importedCount > 0) {
            $entityManager->flush();
        }

        if ($importedCount > 0 && $errors === []) {
            $this->addFlash('success', sprintf('Import terminé : %d élément(s) importé(s).', $importedCount));
        } elseif ($importedCount > 0) {
            $this->addFlash('info', sprintf('Import terminé : %d élément(s) importé(s), avec %d erreur(s).', $importedCount, \count($errors)));
        } else {
            $this->addFlash('error', 'Import échoué. Vérifiez le fichier et réessayez.');
        }

        return $this->json([
            'success' => $importedCount > 0,
            'importedCount' => $importedCount,
            'errors' => $errors,
        ]);
    }

    #[Route('/settings', name: 'app_colis_settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('colis/settings.html.twig');
    }
}
