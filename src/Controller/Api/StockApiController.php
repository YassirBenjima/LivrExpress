<?php

namespace App\Controller\Api;

use App\Entity\Colis;
use App\Entity\StockMovement;
use App\Entity\StockProduct;
use App\Entity\StockProductVariant;
use App\Entity\StockMovementItem;
use App\Repository\ColisRepository;
use App\Repository\StockProductRepository;
use App\Repository\StockMovementRepository;
use App\Repository\StockProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class StockApiController extends AbstractController
{
    // ─────────────────────────────────────────────
    // PRODUCTS
    // ─────────────────────────────────────────────

    #[Route('/api/stock/products', name: 'api_stock_products_list', methods: ['GET'])]
    public function products(Request $request, StockProductRepository $repo): JsonResponse
    {
        $search = trim((string) $request->query->get('q', ''));
        $all = $repo->findBy([], ['id' => 'DESC']);

        $data = [];
        foreach ($all as $product) {
            if (!$product instanceof StockProduct) continue;

            if ($search !== '' && !str_contains(mb_strtolower($product->getName()), mb_strtolower($search))) {
                continue;
            }

            $totalQty = $product->getVariants()->count() > 0
                ? array_sum(array_map(fn(StockProductVariant $v): int => $v->getQuantity(), $product->getVariants()->toArray()))
                : (int) ($product->getQuantity() ?? 0);

            $variantsData = [];
            foreach ($product->getVariants() as $v) {
                if (!$v instanceof StockProductVariant) continue;
                $variantsData[] = [
                    'id'       => $v->getId(),
                    'name'     => $v->getName(),
                    'barcode'  => $v->getBarcode(),
                    'quantity' => $v->getQuantity(),
                ];
            }

            $data[] = [
                'id'         => $product->getId(),
                'name'       => $product->getName(),
                'photo_url'  => $product->getPhotoPath() ? '/' . ltrim($product->getPhotoPath(), '/') : null,
                'barcode'    => $product->getBarcode(),
                'category'   => $product->getCategory(),
                'note'       => $product->getNote(),
                'quantity'   => $totalQty,
                'variants'   => $variantsData,
                'updated_at' => $product->getUpdatedAt() ? $product->getUpdatedAt()->format('d/m/Y H:i') : null,
            ];
        }

        return $this->json([
            'products'       => $data,
            'total_products' => count($data),
            'total_qty'      => array_sum(array_column($data, 'quantity')),
        ]);
    }

    #[Route('/api/stock/products', name: 'api_stock_products_create', methods: ['POST'])]
    public function createProduct(Request $request, EntityManagerInterface $em, StockProductVariantRepository $variantRepo): JsonResponse
    {
        $name     = trim((string) $request->request->get('name', ''));
        $category = trim((string) $request->request->get('category', ''));
        $note     = trim((string) $request->request->get('note', ''));
        $qty      = (int) $request->request->get('quantity', 0);
        $barcode  = trim((string) $request->request->get('barcode', ''));

        if ($name === '') {
            return $this->json(['message' => 'Le nom du produit est obligatoire.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $product = new StockProduct($name, $category ?: '1');
        $product->setNote($note !== '' ? $note : null);
        if ($barcode !== '') $product->setBarcode($barcode);
        $product->setQuantity($qty);

        $em->persist($product);
        $em->flush();

        return $this->json(['message' => 'Produit créé avec succès.', 'id' => $product->getId()]);
    }

    // ─────────────────────────────────────────────
    // STOCK ENTRY MOVEMENTS
    // ─────────────────────────────────────────────

    #[Route('/api/stock/entry', name: 'api_stock_entry_list', methods: ['GET'])]
    public function entryList(Request $request, StockMovementRepository $repo): JsonResponse
    {
        $search = trim((string) $request->query->get('q', ''));
        $movements = $repo->findEntryMovementsForIndex($search);

        $data = [];
        foreach ($movements as $m) {
            if (!$m instanceof StockMovement) continue;

            $products = [];
            foreach ($m->getItems() as $item) {
                $pName = $item->getVariant()?->getProduct()?->getName();
                if (is_string($pName) && $pName !== '') {
                    $products[] = $pName;
                }
            }
            $count = count($products);
            $summary = $count === 0 ? '-' : ($count <= 2 ? implode(', ', $products) : sprintf('%s, +%d', implode(', ', array_slice($products, 0, 2)), $count - 2));

            $data[] = [
                'id'               => $m->getId(),
                'reference'        => $m->getReference(),
                'products_summary' => $summary,
                'products_count'   => $count,
                'status'           => $m->getStatus(),
                'created_at'       => $m->getCreatedAt() ? $m->getCreatedAt()->format('d/m/Y H:i') : null,
                'updated_at'       => $m->getUpdatedAt() ? $m->getUpdatedAt()->format('d/m/Y H:i') : null,
            ];
        }

        return $this->json(['movements' => $data, 'total' => count($data)]);
    }

    #[Route('/api/stock/entry', name: 'api_stock_entry_save', methods: ['POST'])]
    public function entrySave(Request $request, EntityManagerInterface $em, StockMovementRepository $movRepo, StockProductVariantRepository $variantRepo): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $variants = $body['variants'] ?? [];

        if (empty($variants)) {
            return $this->json(['message' => 'Veuillez saisir au moins une quantité.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $reference = $this->generateUniqueRef($movRepo);
        $movement = new StockMovement($reference);
        $movement->setDirection(StockMovement::DIRECTION_ENTRY);
        $movement->setStatus(StockMovement::STATUS_PENDING);
        $em->persist($movement);

        foreach ($variants as $variantId => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) continue;
            $variant = $variantRepo->find((int) $variantId);
            if ($variant instanceof StockProductVariant) {
                $movement->addItem(new StockMovementItem($variant, $qty));
            }
        }

        if ($movement->getItems()->count() === 0) {
            return $this->json(['message' => 'Aucune variante valide sélectionnée.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json(['message' => 'Mouvement de stock enregistré avec succès.', 'id' => $movement->getId()]);
    }

    // ─────────────────────────────────────────────
    // STOCK COLIS (pickup)
    // ─────────────────────────────────────────────

    #[Route('/api/stock/colis', name: 'api_stock_colis_list', methods: ['GET'])]
    public function stockColis(ColisRepository $repo): JsonResponse
    {
        $colisList = $repo->findBy([
            'statut' => Colis::STATUT_EN_ATTENTE,
            'type'   => Colis::TYPE_STOCK,
        ], ['id' => 'DESC']);

        $data = [];
        foreach ($colisList as $colis) {
            $etat   = $colis->getEtat()   ?? Colis::ETAT_CREE;
            $statut = $colis->getStatut() ?? Colis::STATUT_EN_ATTENTE;

            $data[] = [
                'id'           => $colis->getId(),
                'orderNumber'  => $colis->getOrderNumber(),
                'trackingCode' => $colis->getTrackingCode(),
                'productNature'=> $colis->getProductNature() ?: 'Marchandise',
                'createdAt'    => $colis->getCreatedAt() ? $colis->getCreatedAt()->format('d/m/Y H:i') : '',
                'address'      => $colis->getAddress() ?: '-',
                'city'         => $colis->getCity() ?: '-',
                'price'        => (float) ($colis->getPrice() ?? 0.0),
                'etatLabel'    => $etat,
                'etatBadgeClass' => match ($etat) {
                    Colis::ETAT_LIVRE         => 'kt-badge-success',
                    Colis::ETAT_EN_PREPARATION => 'kt-badge-warning',
                    Colis::ETAT_EXPEDIE        => 'kt-badge-info',
                    Colis::ETAT_RETOUR         => 'kt-badge-destructive',
                    default                    => 'kt-badge-primary',
                },
                'statutLabel'  => $statut,
                'statutBadgeClass' => match ($statut) {
                    Colis::STATUT_TERMINE => 'kt-badge-success',
                    Colis::STATUT_REPORTE => 'kt-badge-warning',
                    Colis::STATUT_ECHEC   => 'kt-badge-destructive',
                    default               => 'kt-badge-primary',
                },
                'comment'      => $colis->getComment() ?: '-',
            ];
        }

        return $this->json($data);
    }

    // ─────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────

    private function generateUniqueRef(StockMovementRepository $repo): string
    {
        do {
            $ref = 'ENT-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while ($repo->findOneBy(['reference' => $ref]) !== null);

        return $ref;
    }
}
