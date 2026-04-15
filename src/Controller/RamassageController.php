<?php

namespace App\Controller;

use App\Entity\PickupRequest;
use App\Entity\User;
use App\Repository\CityRepository;
use App\Repository\PickupRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/ramassage')]
final class RamassageController extends AbstractController
{
    private const STATUS_LABELS = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmé',
        'picked_up' => 'Ramassé',
        'cancelled' => 'Annulé',
    ];

    #[Route('', name: 'app_ramassage_index', methods: ['GET'])]
    public function index(Request $request, PickupRequestRepository $pickupRequestRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $selectedStatut = trim((string) $request->query->get('statut', ''));

        $pickups = $pickupRequestRepository->findAllForList($search, $selectedStatut);

        return $this->render('ramassage/index.html.twig', [
            'pickups' => $pickups,
            'statuts_possibles' => ['pending', 'confirmed', 'picked_up', 'cancelled'],
            'statut_labels' => self::STATUS_LABELS,
            'search_query' => $search,
            'selected_statut' => $selectedStatut,
        ]);
    }

    #[Route('/new', name: 'app_ramassage_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CityRepository $cityRepository,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ramassage_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('app_ramassage_new');
            }

            $user = $this->getUser();
            if (!$user instanceof User) {
                $this->addFlash('error', 'Vous devez être connecté.');

                return $this->redirectToRoute('app_login');
            }

            $phone = trim((string) $request->request->get('phone', ''));
            $supplierPhone = trim((string) $request->request->get('supplier_phone', ''));
            $city = trim((string) $request->request->get('city', ''));
            $neighborhood = trim((string) $request->request->get('neighborhood', ''));
            $address = trim((string) $request->request->get('address', ''));
            $productName = trim((string) $request->request->get('product_name', ''));
            $note = trim((string) $request->request->get('note', ''));
            $hasLabelsRaw = (string) $request->request->get('has_labels', '1');
            $type = trim((string) $request->request->get('type', 'simple'));

            if ($phone === '') {
                $this->addFlash('error', 'Le numéro de téléphone est obligatoire.');

                return $this->redirectToRoute('app_ramassage_new');
            }
            if ($city === '') {
                $this->addFlash('error', 'La ville est obligatoire.');

                return $this->redirectToRoute('app_ramassage_new');
            }
            if ($cityRepository->count(['name' => $city]) === 0) {
                $this->addFlash('error', 'Veuillez choisir une ville valide.');

                return $this->redirectToRoute('app_ramassage_new');
            }
            if ($neighborhood === '') {
                $this->addFlash('error', 'Le quartier est obligatoire.');

                return $this->redirectToRoute('app_ramassage_new');
            }
            if ($address === '') {
                $this->addFlash('error', "L'adresse est obligatoire.");

                return $this->redirectToRoute('app_ramassage_new');
            }
            if ($productName === '') {
                $this->addFlash('error', 'La nature du produit est obligatoire.');

                return $this->redirectToRoute('app_ramassage_new');
            }

            $pickup = new PickupRequest();
            $pickup->setPhone($phone);
            $pickup->setSupplierPhone($supplierPhone !== '' ? $supplierPhone : null);
            $pickup->setCity($city);
            $pickup->setNeighborhood($neighborhood);
            $pickup->setAddress($address);
            $pickup->setProductNameSnapshot($productName);
            $pickup->setNote($note !== '' ? $note : null);
            $pickup->setHasLabels($hasLabelsRaw === '1');
            $pickup->setType(\in_array($type, ['simple', 'stock'], true) ? $type : 'simple');
            $pickup->setCreatedBy($user);
            $pickup->setStatus('pending');

            $entityManager->persist($pickup);
            $entityManager->flush();

            $this->addFlash('success', 'Demande de ramassage créée avec succès.');

            return $this->redirectToRoute('app_ramassage_index');
        }

        // GET — show form
        $cities = [];
        foreach ($cityRepository->findBy([], ['name' => 'ASC']) as $city) {
            $cities[] = (string) $city->getName();
        }

        return $this->render('ramassage/new.html.twig', [
            'cities' => $cities,
        ]);
    }

    #[Route('/planning', name: 'app_ramassage_planning', methods: ['GET'])]
    public function planning(PickupRequestRepository $pickupRequestRepository): Response
    {
        $grouped = $pickupRequestRepository->findGroupedByStatus();
        $stats = $pickupRequestRepository->countByStatus();

        return $this->render('ramassage/planning.html.twig', [
            'grouped' => $grouped,
            'stats' => $stats,
            'statut_labels' => self::STATUS_LABELS,
        ]);
    }

    #[Route('/{id}/status', name: 'app_ramassage_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, PickupRequest $pickup, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('ramassage_status_' . $pickup->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_ramassage_planning');
        }

        $newStatus = trim((string) $request->request->get('status', ''));
        $allowed = ['pending', 'confirmed', 'picked_up', 'cancelled'];
        if (!\in_array($newStatus, $allowed, true)) {
            $this->addFlash('error', 'Statut invalide.');

            return $this->redirectToRoute('app_ramassage_planning');
        }

        $pickup->setStatus($newStatus);
        $entityManager->flush();

        $labels = self::STATUS_LABELS;
        $this->addFlash('success', sprintf('Statut mis à jour : %s.', $labels[$newStatus] ?? $newStatus));

        $redirectTo = (string) $request->request->get('redirect', 'planning');
        if ($redirectTo === 'index') {
            return $this->redirectToRoute('app_ramassage_index');
        }

        return $this->redirectToRoute('app_ramassage_planning');
    }
}
