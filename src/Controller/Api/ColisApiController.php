<?php

namespace App\Controller\Api;

use App\Entity\Colis;
use App\Repository\ColisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ColisApiController extends AbstractController
{
    #[Route('/api/colis', name: 'api_colis_list', methods: ['GET'])]
    public function getColis(ColisRepository $colisRepository): JsonResponse
    {
        $colisList = $colisRepository->findBy([], ['id' => 'DESC']);
        $data = [];

        foreach ($colisList as $colis) {
            $statut = $colis->getStatut() ?? Colis::STATUT_EN_ATTENTE;
            $etat = $colis->getEtat() ?? Colis::ETAT_CREE;

            $data[] = [
                'id' => $colis->getId(),
                'orderNumber' => $colis->getOrderNumber(),
                'trackingCode' => $colis->getTrackingCode(),
                'productNature' => $colis->getProductNature() ?: 'Marchandise',
                'createdAt' => $colis->getCreatedAt() ? $colis->getCreatedAt()->format('d/m/Y H:i') : '',
                'address' => $colis->getAddress() ?: '-',
                'etatLabel' => $etat,
                'etatBadgeClass' => match ($etat) {
                    Colis::ETAT_LIVRE => 'kt-badge-success',
                    Colis::ETAT_EN_PREPARATION => 'kt-badge-warning',
                    Colis::ETAT_EXPEDIE => 'kt-badge-info',
                    Colis::ETAT_RETOUR => 'kt-badge-destructive',
                    default => 'kt-badge-primary',
                },
                'statutLabel' => $statut,
                'statutBadgeClass' => match ($statut) {
                    Colis::STATUT_TERMINE => 'kt-badge-success',
                    Colis::STATUT_REPORTE => 'kt-badge-warning',
                    Colis::STATUT_ECHEC => 'kt-badge-destructive',
                    default => 'kt-badge-primary',
                },
                'city' => $colis->getCity() ?: '-',
                'price' => (float) ($colis->getPrice() ?? 0.0),
                'comment' => $colis->getComment() ?: '-',
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/colis/new', name: 'api_colis_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $colis = new Colis();
        
        // Map fields
        $colis->setOrderNumber($data['orderNumber'] ?? '');
        $colis->setType($data['type'] ?? Colis::TYPE_SIMPLE);
        $colis->setRecipient($data['recipient'] ?? null);
        $colis->setCity($data['city'] ?? '');
        $colis->setAddress($data['address'] ?? '');
        $colis->setPrice((string)($data['price'] ?? 0.0));
        $colis->setPhoneNumber($data['phoneNumber'] ?? '');
        $colis->setNeighborhood($data['neighborhood'] ?? '');
        $colis->setProductNature($data['productNature'] ?? 'Marchandise');
        $colis->setComment($data['comment'] ?? null);
        $colis->setPackageOption($data['packageOption'] ?? 'Ne pas ouvrir le colis');
        $colis->setReplacePackage((bool)($data['replacePackage'] ?? false));
        
        if ($colis->isReplacePackage() && !empty($data['oldColis'])) {
            $colis->setOldOrderNumber($data['oldColis']);
        } else {
            $colis->setOldOrderNumber(null);
        }

        $colis->setFragile((bool)($data['fragile'] ?? false));
        $colis->setAllFragile((bool)($data['allFragile'] ?? false));
        
        $useCarton = (bool)($data['useCarton'] ?? false);
        if ($useCarton && !empty($data['cartonOption'])) {
            $colis->setCartonOption($data['cartonOption']);
        } else {
            $colis->setCartonOption(null);
        }

        try {
            $entityManager->persist($colis);
            $entityManager->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return $this->json(['message' => 'Numero de commande deja utilise. Veuillez saisir un numero unique.'], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Une erreur est survenue lors de l\'ajout: ' . $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['message' => 'Colis ajoute avec succes.', 'id' => $colis->getId()]);
    }

    #[Route('/api/colis/pickup', name: 'api_colis_pickup', methods: ['GET'])]
    public function getPickupColis(ColisRepository $colisRepository): JsonResponse
    {
        $colisList = $colisRepository->findBy([
            'statut' => Colis::STATUT_EN_ATTENTE,
            'type' => Colis::TYPE_SIMPLE
        ], ['id' => 'DESC']);
        $data = [];

        foreach ($colisList as $colis) {
            $statut = $colis->getStatut() ?? Colis::STATUT_EN_ATTENTE;
            $etat = $colis->getEtat() ?? Colis::ETAT_CREE;

            $data[] = [
                'id' => $colis->getId(),
                'orderNumber' => $colis->getOrderNumber(),
                'trackingCode' => $colis->getTrackingCode(),
                'productNature' => $colis->getProductNature() ?: 'Marchandise',
                'createdAt' => $colis->getCreatedAt() ? $colis->getCreatedAt()->format('d/m/Y H:i') : '',
                'address' => $colis->getAddress() ?: '-',
                'etatLabel' => $etat,
                'etatBadgeClass' => match ($etat) {
                    Colis::ETAT_LIVRE => 'kt-badge-success',
                    Colis::ETAT_EN_PREPARATION => 'kt-badge-warning',
                    Colis::ETAT_EXPEDIE => 'kt-badge-info',
                    Colis::ETAT_RETOUR => 'kt-badge-destructive',
                    default => 'kt-badge-primary',
                },
                'statutLabel' => $statut,
                'statutBadgeClass' => match ($statut) {
                    Colis::STATUT_TERMINE => 'kt-badge-success',
                    Colis::STATUT_REPORTE => 'kt-badge-warning',
                    Colis::STATUT_ECHEC => 'kt-badge-destructive',
                    default => 'kt-badge-primary',
                },
                'city' => $colis->getCity() ?: '-',
                'price' => (float) ($colis->getPrice() ?? 0.0),
                'comment' => $colis->getComment() ?: '-',
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/colis/request-pickup-bulk', name: 'api_colis_request_pickup_bulk', methods: ['POST'])]
    public function requestPickupBulk(Request $request, ColisRepository $colisRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];

        if (!\is_array($ids) || $ids === []) {
            return $this->json(['message' => 'Aucun colis sélectionné.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $updated = 0;
        foreach ($ids as $id) {
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
            return $this->json(['message' => sprintf('%d colis envoyé(s) en demande de ramassage.', $updated)]);
        }

        return $this->json(['message' => 'Aucun colis valide à traiter.'], JsonResponse::HTTP_BAD_REQUEST);
    }
}
