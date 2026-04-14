<?php

namespace App\Controller;

use App\Entity\Colis;
use App\Entity\PickupRequest;
use App\Entity\StockProduct;
use App\Entity\StockProductVariant;
use App\Entity\User;
use App\Repository\CityRepository;
use App\Repository\ColisRepository;
use App\Repository\PickupRequestRepository;
use App\Repository\StockProductRepository;
use App\Repository\StockProductVariantRepository;
use App\Service\StockProductMediaManager;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/stock')]
final class StockController extends AbstractController
{
    #[Route('/colis', name: 'app_stock_colis_pickup', methods: ['GET'])]
    public function colisPickup(Request $request, ColisRepository $colisRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $selectedEtat = self::normalizeEtat(trim((string) $request->query->get('etat', '')));
        $selectedStatut = self::normalizeStatut(trim((string) $request->query->get('statut', '')));

        $colisList = $colisRepository->findBy([], ['id' => 'DESC']);
        $colisList = array_values(array_filter($colisList, static function (Colis $colis) use ($search, $selectedEtat, $selectedStatut): bool {
            $etat = self::normalizeEtat((string) ($colis->getEtat() ?? Colis::ETAT_CREE));
            $statut = self::normalizeStatut((string) ($colis->getStatut() ?? Colis::STATUT_EN_ATTENTE));

            // Same "pour ramassage" logic: only waiting packages.
            if ($statut !== Colis::STATUT_EN_ATTENTE) {
                return false;
            }

            // Business rule: Stock pickup page shows only "Colis du stock".
            if ($colis->getType() !== Colis::TYPE_STOCK) {
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

        return $this->render('stock/colis/pickup.html.twig', [
            'colis_list' => $colisList,
            'etats_possibles' => array_values($pickupEtats),
            'statuts_possibles' => array_values($pickupStatuts),
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

    #[Route('/produits', name: 'app_stock_products_index', methods: ['GET'])]
    public function productsIndex(
        Request $request,
        StockProductRepository $stockProductRepository,
        CityRepository $cityRepository,
        PickupRequestRepository $pickupRequestRepository,
    ): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $all = $stockProductRepository->findBy([], ['id' => 'DESC']);

        $products = [];
        foreach ($all as $product) {
            if (!$product instanceof StockProduct) {
                continue;
            }

            if ($search !== '' && !str_contains(mb_strtolower($product->getName()), mb_strtolower($search))) {
                continue;
            }

            // At creation time, quantity is considered "not received" (pending),
            // while "received" starts at 0.
            $totalQty = $product->getVariants()->count() > 0
                ? array_sum(array_map(static fn (StockProductVariant $v): int => $v->getQuantity(), $product->getVariants()->toArray()))
                : (int) ($product->getQuantity() ?? 0);

            $variantsCount = $product->getVariants()->count();
            $firstVariantBarcode = null;
            $variants = [];
            if ($variantsCount > 0) {
                foreach ($product->getVariants() as $variant) {
                    if (!$variant instanceof StockProductVariant) {
                        continue;
                    }
                    if ($variant->getBarcode()) {
                        $firstVariantBarcode = $variant->getBarcode();
                    }

                    $variants[] = [
                        'id' => $variant->getId(),
                        'name' => $variant->getName(),
                        'ref_or_barcode' => $variant->getBarcode() ?: null,
                        'qty_received' => 0,
                        'qty_not_received' => $variant->getQuantity(),
                    ];
                }
            }

            $products[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'photo_url' => $product->getPhotoPath() ? '/' . ltrim($product->getPhotoPath(), '/') : null,
                'ref_or_barcode' => $product->getBarcode() ?: null,
                'has_variants' => $variantsCount > 0,
                'variants_count' => $variantsCount,
                'variants_first_barcode' => $firstVariantBarcode,
                'variants' => $variants,
                'qty_received' => 0,
                'qty_not_received' => $totalQty,
                'last_in_at' => $product->getUpdatedAt(),
            ];
        }

        // Expose "pickup already requested" without N+1.
        $productIds = array_values(array_filter(array_map(
            static fn (array $p): int => (int) ($p['id'] ?? 0),
            $products
        ), static fn (int $v): bool => $v > 0));
        $requestedIds = $pickupRequestRepository->findProductIdsWithPendingRequests($productIds);
        $requestedSet = array_fill_keys($requestedIds, true);
        foreach ($products as $i => $p) {
            $id = (int) ($p['id'] ?? 0);
            $products[$i]['pickup_requested'] = $id > 0 && isset($requestedSet[$id]);
        }

        $totalProducts = \count($products);
        $totalQty = array_sum(array_map(
            static fn (array $p): int => (int) ($p['qty_received'] ?? 0) + (int) ($p['qty_not_received'] ?? 0),
            $products
        ));

        $cities = [];
        foreach ($cityRepository->findBy([], ['name' => 'ASC']) as $city) {
            $cities[] = (string) $city->getName();
        }

        $user = $this->getUser();
        $defaultCity = $user instanceof User ? (string) ($user->getCity() ?? '') : '';

        return $this->render('stock/products/index.html.twig', [
            'products' => $products,
            'search_query' => $search,
            'total_products' => $totalProducts,
            'total_qty' => $totalQty,
            'cities' => $cities,
            'default_city' => $defaultCity,
        ]);
    }

    #[Route('/produits/{id}/pickup-request', name: 'app_stock_products_pickup_request_create', methods: ['POST'])]
    public function pickupRequestCreate(
        int $id,
        Request $request,
        StockProductRepository $stockProductRepository,
        PickupRequestRepository $pickupRequestRepository,
        CityRepository $cityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $product = $stockProductRepository->find($id);
        if (!$product instanceof StockProduct) {
            $this->addFlash('error', 'Produit introuvable.');

            return $this->redirectToRoute('app_stock_products_index');
        }

        if (!$this->isCsrfTokenValid('pickup_request_' . $product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_stock_products_index');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté pour effectuer cette action.');

            return $this->redirectToRoute('app_login');
        }

        if ($pickupRequestRepository->hasPendingForProductId((int) $product->getId())) {
            $this->addFlash('warning', 'Une demande de ramassage est déjà en attente pour ce produit.');

            return $this->redirectToRoute('app_stock_products_index');
        }

        $city = trim((string) $request->request->get('city', ''));
        $neighborhood = trim((string) $request->request->get('neighborhood', ''));
        $address = trim((string) $request->request->get('address', ''));
        $phone = trim((string) $request->request->get('phone', ''));
        $supplierPhone = trim((string) $request->request->get('supplier_phone', ''));
        $note = trim((string) $request->request->get('note', ''));
        $hasLabelsRaw = (string) $request->request->get('has_labels', '');

        if ($city === '') {
            $this->addFlash('error', 'La ville est obligatoire.');

            return $this->redirectToRoute('app_stock_products_index');
        }
        if ($cityRepository->count(['name' => $city]) === 0) {
            $this->addFlash('error', 'Veuillez choisir une ville valide.');

            return $this->redirectToRoute('app_stock_products_index');
        }
        if ($neighborhood === '') {
            $this->addFlash('error', 'Le quartier est obligatoire.');

            return $this->redirectToRoute('app_stock_products_index');
        }
        if ($address === '') {
            $this->addFlash('error', 'L’adresse est obligatoire.');

            return $this->redirectToRoute('app_stock_products_index');
        }
        if ($phone === '') {
            $this->addFlash('error', 'Le téléphone est obligatoire.');

            return $this->redirectToRoute('app_stock_products_index');
        }
        if (!\in_array($hasLabelsRaw, ['0', '1'], true)) {
            $this->addFlash('error', 'Veuillez indiquer si vous avez les étiquettes.');

            return $this->redirectToRoute('app_stock_products_index');
        }

        $pickupRequest = new PickupRequest();
        $pickupRequest->setProduct($product);
        $pickupRequest->setProductNameSnapshot($product->getName());
        $pickupRequest->setCity($city);
        $pickupRequest->setNeighborhood($neighborhood);
        $pickupRequest->setAddress($address);
        $pickupRequest->setPhone($phone);
        $pickupRequest->setSupplierPhone($supplierPhone !== '' ? $supplierPhone : null);
        $pickupRequest->setNote($note !== '' ? $note : null);
        $pickupRequest->setHasLabels($hasLabelsRaw === '1');
        $pickupRequest->setCreatedBy($user);
        $pickupRequest->setStatus('pending');

        $entityManager->persist($pickupRequest);
        $entityManager->flush();

        $this->addFlash('success', 'Demande de ramassage enregistrée avec succès.');

        return $this->redirectToRoute('app_stock_products_index');
    }

    #[Route('/produits/new', name: 'app_stock_products_new', methods: ['GET'])]
    public function productsNew(): Response
    {
        return $this->render('stock/products/new.html.twig', [
            'is_edit_mode' => false,
            'product' => null,
        ]);
    }

    #[Route('/produits', name: 'app_stock_products_create', methods: ['POST'])]
    public function productsCreate(
        Request $request,
        EntityManagerInterface $entityManager,
        StockProductVariantRepository $stockProductVariantRepository,
        StockProductMediaManager $mediaManager,
    ): Response
    {
        if (!$this->isCsrfTokenValid('stock_product_new', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');

            return $this->redirectToRoute('app_stock_products_new');
        }

        $name = trim((string) $request->request->get('name', ''));
        $category = trim((string) $request->request->get('category', ''));
        $variantsEnabled = (bool) $request->request->get('variants_enabled');

        // Category select in the form submits numeric IDs (1..11)
        $allowedCategories = array_map('strval', range(1, 11));
        if ($name === '') {
            $this->addFlash('error', 'Le nom du produit est obligatoire.');

            return $this->redirectToRoute('app_stock_products_new');
        }
        if ($category === '' || !\in_array($category, $allowedCategories, true)) {
            $this->addFlash('error', 'Veuillez choisir une catégorie valide.');

            return $this->redirectToRoute('app_stock_products_new');
        }

        $product = new StockProduct($name, $category);
        $product->setNote($request->request->get('note'));

        if ($variantsEnabled) {
            /** @var array<int, array{barcode?: mixed, name?: mixed, quantity?: mixed}> $variants */
            $variants = $request->request->all('variants');

            $hasAtLeastOne = false;
            foreach ($variants as $row) {
                $vName = trim((string) ($row['name'] ?? ''));
                $vBarcode = trim((string) ($row['barcode'] ?? ''));
                $vQtyRaw = (string) ($row['quantity'] ?? '');
                $vQty = $vQtyRaw !== '' ? (int) $vQtyRaw : null;

                if ($vName === '' && $vBarcode === '' && ($vQty === null || $vQty === 0)) {
                    continue;
                }

                $hasAtLeastOne = true;
                if ($vName === '') {
                    $this->addFlash('error', 'Chaque variante doit avoir un nom.');

                    return $this->redirectToRoute('app_stock_products_new');
                }
                if ($vQty === null || $vQty < 0) {
                    $this->addFlash('error', 'La quantité de chaque variante est obligatoire et doit être valide.');

                    return $this->redirectToRoute('app_stock_products_new');
                }

                $variant = new StockProductVariant($vName, $vQty);
                $variant->setBarcode($vBarcode !== '' ? $vBarcode : null);
                if ($variant->getBarcode() === null) {
                    $variant->setBarcode($this->generateUniqueBarcode($entityManager, $stockProductVariantRepository));
                }
                $product->addVariant($variant);
            }

            if (!$hasAtLeastOne) {
                $this->addFlash('error', 'Ajoutez au moins une variante ou désactivez le mode variantes.');

                return $this->redirectToRoute('app_stock_products_new');
            }

            // Ensure product also has its own barcode (mandatory)
            if ($product->getBarcode() === null) {
                $product->setBarcode($this->generateUniqueBarcode($entityManager, $stockProductVariantRepository));
            }
        } else {
            $product->setBarcode($request->request->get('barcode'));
            if ($product->getBarcode() === null) {
                $product->setBarcode($this->generateUniqueBarcode($entityManager, $stockProductVariantRepository));
            }

            $qtyRaw = trim((string) $request->request->get('quantity', ''));
            if ($qtyRaw === '' || !ctype_digit($qtyRaw)) {
                $this->addFlash('error', 'La quantité est obligatoire.');

                return $this->redirectToRoute('app_stock_products_new');
            }
            $product->setQuantity((int) $qtyRaw);
        }

        $photo = $request->files->get('photo');
        if ($photo instanceof UploadedFile) {
            if (!$photo->isValid()) {
                $this->addFlash('error', 'Le fichier image est invalide.');

                return $this->redirectToRoute('app_stock_products_new');
            }

            $mime = (string) $photo->getMimeType();
            if (!\in_array($mime, ['image/jpeg', 'image/png'], true)) {
                $this->addFlash('error', 'Format image non supporté. Utilisez JPG ou PNG.');

                return $this->redirectToRoute('app_stock_products_new');
            }
        }

        $entityManager->persist($product);

        $newPhotoPath = null;
        $newQrPath = null;
        try {
            if ($photo instanceof UploadedFile) {
                $newPhotoPath = $mediaManager->uploadProductPhoto($photo);
                $product->setPhotoPath($newPhotoPath);
            }

            // QR code is generated at creation time (mandatory)
            $barcodeForQr = $product->getBarcode();
            if ($barcodeForQr !== null) {
                $newQrPath = $mediaManager->generateProductQrPng($barcodeForQr);
                $product->setQrCodePath($newQrPath);
            }

            $entityManager->flush();
        } catch (\Throwable $e) {
            // Cleanup any new file created during this request to avoid orphans.
            $mediaManager->deletePublicFileSafely($newPhotoPath);
            $mediaManager->deletePublicFileSafely($newQrPath);

            $this->addFlash('error', 'Erreur lors de l’enregistrement du produit.');

            return $this->redirectToRoute('app_stock_products_new');
        }

        $this->addFlash('success', 'Produit enregistré avec succès.');

        return $this->redirectToRoute('app_stock_products_index');
    }

    #[Route('/produits/{id}/edit', name: 'app_stock_products_edit', methods: ['GET'])]
    public function productsEdit(int $id, StockProductRepository $stockProductRepository): Response
    {
        $product = $stockProductRepository->find($id);
        if (!$product instanceof StockProduct) {
            $this->addFlash('error', 'Produit introuvable.');

            return $this->redirectToRoute('app_stock_products_index');
        }

        return $this->render('stock/products/new.html.twig', [
            'is_edit_mode' => true,
            'product' => $product,
        ]);
    }

    #[Route('/produits/{id}/update', name: 'app_stock_products_update', methods: ['POST'])]
    public function productsUpdate(
        int $id,
        Request $request,
        StockProductRepository $stockProductRepository,
        StockProductVariantRepository $stockProductVariantRepository,
        EntityManagerInterface $entityManager,
        StockProductMediaManager $mediaManager,
    ): Response {
        $product = $stockProductRepository->find($id);
        if (!$product instanceof StockProduct) {
            $this->addFlash('error', 'Produit introuvable.');

            return $this->redirectToRoute('app_stock_products_index');
        }

        if (!$this->isCsrfTokenValid('stock_product_edit_'.$product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_stock_products_index');
        }

        $name = trim((string) $request->request->get('name', ''));
        $category = trim((string) $request->request->get('category', ''));
        $variantsEnabled = (bool) $request->request->get('variants_enabled');

        $allowedCategories = array_map('strval', range(1, 11));
        if ($name === '') {
            $this->addFlash('error', 'Le nom du produit est obligatoire.');

            return $this->redirectToRoute('app_stock_products_edit', ['id' => $product->getId()]);
        }
        if ($category === '' || !\in_array($category, $allowedCategories, true)) {
            $this->addFlash('error', 'Veuillez choisir une catégorie valide.');

            return $this->redirectToRoute('app_stock_products_edit', ['id' => $product->getId()]);
        }

        $oldPhotoPath = $product->getPhotoPath();
        $oldQrPath = $product->getQrCodePath();

        $product->setName($name);
        $product->setCategory($category);
        $product->setNote($request->request->get('note'));

        if ($variantsEnabled) {
            // Replace variants with submitted ones
            foreach ($product->getVariants()->toArray() as $existing) {
                if ($existing instanceof StockProductVariant) {
                    $product->removeVariant($existing);
                }
            }

            /** @var array<int, array{barcode?: mixed, name?: mixed, quantity?: mixed}> $variants */
            $variants = $request->request->all('variants');
            $hasAtLeastOne = false;
            foreach ($variants as $row) {
                $vName = trim((string) ($row['name'] ?? ''));
                $vBarcode = trim((string) ($row['barcode'] ?? ''));
                $vQtyRaw = (string) ($row['quantity'] ?? '');
                $vQty = $vQtyRaw !== '' ? (int) $vQtyRaw : null;

                if ($vName === '' && $vBarcode === '' && ($vQty === null || $vQty === 0)) {
                    continue;
                }

                $hasAtLeastOne = true;
                if ($vName === '' || $vQty === null || $vQty < 0) {
                    $this->addFlash('error', 'Chaque variante doit avoir un nom et une quantité valide.');

                    return $this->redirectToRoute('app_stock_products_edit', ['id' => $product->getId()]);
                }

                $variant = new StockProductVariant($vName, $vQty);
                $variant->setBarcode($vBarcode !== '' ? $vBarcode : null);
                if ($variant->getBarcode() === null) {
                    $variant->setBarcode($this->generateUniqueBarcode($entityManager, $stockProductVariantRepository));
                }
                $product->addVariant($variant);
            }

            if (!$hasAtLeastOne) {
                $this->addFlash('error', 'Ajoutez au moins une variante ou désactivez le mode variantes.');

                return $this->redirectToRoute('app_stock_products_edit', ['id' => $product->getId()]);
            }

            if ($product->getBarcode() === null) {
                $product->setBarcode($this->generateUniqueBarcode($entityManager, $stockProductVariantRepository));
            }
            $product->setQuantity(null);
        } else {
            // No variants: keep quantity + barcode on product
            $product->setBarcode($request->request->get('barcode'));
            if ($product->getBarcode() === null) {
                $product->setBarcode($this->generateUniqueBarcode($entityManager, $stockProductVariantRepository));
            }

            $qtyRaw = trim((string) $request->request->get('quantity', ''));
            if ($qtyRaw === '' || !ctype_digit($qtyRaw)) {
                $this->addFlash('error', 'La quantité est obligatoire.');

                return $this->redirectToRoute('app_stock_products_edit', ['id' => $product->getId()]);
            }
            $product->setQuantity((int) $qtyRaw);
        }

        $photo = $request->files->get('photo');
        if ($photo instanceof UploadedFile) {
            if (!$photo->isValid()) {
                $this->addFlash('error', 'Le fichier image est invalide.');

                return $this->redirectToRoute('app_stock_products_edit', ['id' => $product->getId()]);
            }

            $mime = (string) $photo->getMimeType();
            if (!\in_array($mime, ['image/jpeg', 'image/png'], true)) {
                $this->addFlash('error', 'Format image non supporté. Utilisez JPG ou PNG.');

                return $this->redirectToRoute('app_stock_products_edit', ['id' => $product->getId()]);
            }
        }

        $newPhotoPath = null;
        $newQrPath = null;
        try {
            if ($photo instanceof UploadedFile) {
                $newPhotoPath = $mediaManager->uploadProductPhoto($photo);
                $product->setPhotoPath($newPhotoPath);
            }

            // Always regenerate QR on every update (simple + avoids stale QR payload).
            $barcodeForQr = $product->getBarcode();
            if ($barcodeForQr !== null) {
                $newQrPath = $mediaManager->generateProductQrPng($barcodeForQr);
                $product->setQrCodePath($newQrPath);
            }

            $entityManager->flush();
        } catch (\Throwable $e) {
            // Cleanup any new file created during this request to avoid orphans.
            $mediaManager->deletePublicFileSafely($newPhotoPath);
            $mediaManager->deletePublicFileSafely($newQrPath);

            $this->addFlash('error', 'Erreur lors de la modification du produit.');

            return $this->redirectToRoute('app_stock_products_edit', ['id' => $product->getId()]);
        }

        // Delete replaced files after a successful flush (atomic-ish).
        if ($newPhotoPath !== null && $oldPhotoPath !== null && $oldPhotoPath !== $newPhotoPath) {
            $mediaManager->deletePublicFileSafely($oldPhotoPath);
        }
        if ($newQrPath !== null && $oldQrPath !== null && $oldQrPath !== $newQrPath) {
            $mediaManager->deletePublicFileSafely($oldQrPath);
        }

        $this->addFlash('success', 'Produit modifié avec succès.');

        return $this->redirectToRoute('app_stock_products_index');
    }

    #[Route('/produits/{id}/delete', name: 'app_stock_products_delete', methods: ['POST'])]
    public function productsDelete(
        int $id,
        Request $request,
        StockProductRepository $stockProductRepository,
        EntityManagerInterface $entityManager,
        StockProductMediaManager $mediaManager,
    ): Response {
        $product = $stockProductRepository->find($id);
        if (!$product instanceof StockProduct) {
            $this->addFlash('error', 'Produit introuvable.');

            return $this->redirectToRoute('app_stock_products_index');
        }

        if (!$this->isCsrfTokenValid('delete_stock_product_'.$product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_stock_products_index');
        }

        $oldPhotoPath = $product->getPhotoPath();
        $oldQrPath = $product->getQrCodePath();

        $entityManager->remove($product);
        try {
            $entityManager->flush();
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de la suppression du produit.');

            return $this->redirectToRoute('app_stock_products_index');
        }

        // Best effort cleanup after DB success.
        $mediaManager->deletePublicFileSafely($oldPhotoPath);
        $mediaManager->deletePublicFileSafely($oldQrPath);

        $this->addFlash('success', 'Produit supprimé avec succès.');

        return $this->redirectToRoute('app_stock_products_index');
    }

    #[Route('/produits/variant/{id}/sticker', name: 'app_stock_products_variant_sticker', methods: ['GET'])]
    public function productVariantSticker(
        int $id,
        StockProductVariantRepository $stockProductVariantRepository,
        Request $request,
        #[Autowire('%stock_products_sticker_logo_url%')] ?string $stickerLogoUrl,
    ): Response {
        $variant = $stockProductVariantRepository->find($id);
        if (!$variant instanceof StockProductVariant) {
            throw $this->createNotFoundException('Variante introuvable.');
        }

        $barcode = $variant->getBarcode() ?? '';
        $qrDataUri = null;
        if ($barcode !== '') {
            $qrCode = new QrCode(
                data: $barcode,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: 220,
                margin: 0,
            );
            $qrDataUri = (new PngWriter())->write($qrCode)->getDataUri();
        }

        if ($request->query->get('html') === '1') {
            return $this->render('stock/products/variant_sticker.html.twig', [
                'variant' => $variant,
                'product' => $variant->getProduct(),
                'barcode' => $barcode,
                'qr_image_url' => null,
                'qr_data_uri' => $qrDataUri,
            ]);
        }

        return $this->renderStickerPdf(
            product: $variant->getProduct(),
            variant: $variant,
            barcode: $barcode,
            qrDataUri: $qrDataUri,
            stickerLogoUrl: $stickerLogoUrl,
            filename: sprintf('sticker-variant-%d.pdf', (int) $variant->getId()),
        );
    }

    #[Route('/produits/variant/{id}/sticker.pdf', name: 'app_stock_products_variant_sticker_pdf', methods: ['GET'])]
    public function productVariantStickerPdfRedirect(int $id): Response
    {
        return $this->redirectToRoute('app_stock_products_variant_sticker', ['id' => $id]);
    }

    #[Route('/produits/{id}/sticker', name: 'app_stock_products_sticker', methods: ['GET'])]
    public function productSticker(
        int $id,
        StockProductRepository $stockProductRepository,
        Request $request,
        #[Autowire('%stock_products_sticker_logo_url%')] ?string $stickerLogoUrl,
    ): Response {
        $product = $stockProductRepository->find($id);
        if (!$product instanceof StockProduct) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $barcode = $product->getBarcode() ?? '';
        $qrImageUrl = $product->getQrCodePath() ? '/' . ltrim($product->getQrCodePath(), '/') : null;
        $qrDataUri = null;
        if ($qrImageUrl === null && $barcode !== '') {
            $qrCode = new QrCode(
                data: $barcode,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: 220,
                margin: 0,
            );
            $qrDataUri = (new PngWriter())->write($qrCode)->getDataUri();
        }

        if ($request->query->get('html') === '1') {
            return $this->render('stock/products/variant_sticker.html.twig', [
                'variant' => null,
                'product' => $product,
                'barcode' => $barcode,
                'qr_image_url' => $qrImageUrl,
                'qr_data_uri' => $qrDataUri,
            ]);
        }

        // For the PDF we always embed as data-uri (Dompdf safe).
        if ($qrImageUrl !== null && $barcode !== '') {
            // Try to reuse the stored PNG; if not readable, fallback.
            $abs = $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . ltrim($product->getQrCodePath() ?? '', '/\\');
            if (is_file($abs)) {
                $bytes = @file_get_contents($abs);
                if (is_string($bytes) && $bytes !== '') {
                    $qrDataUri = 'data:image/png;base64,' . base64_encode($bytes);
                }
            }
            if ($qrDataUri === null) {
                $qrCode = new QrCode(
                    data: $barcode,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: ErrorCorrectionLevel::Low,
                    size: 220,
                    margin: 0,
                );
                $qrDataUri = (new PngWriter())->write($qrCode)->getDataUri();
            }
        }

        return $this->renderStickerPdf(
            product: $product,
            variant: null,
            barcode: $barcode,
            qrDataUri: $qrDataUri,
            stickerLogoUrl: $stickerLogoUrl,
            filename: sprintf('sticker-product-%d.pdf', (int) $product->getId()),
        );
    }

    #[Route('/produits/{id}/sticker.pdf', name: 'app_stock_products_sticker_pdf', methods: ['GET'])]
    public function productStickerPdfRedirect(int $id): Response
    {
        return $this->redirectToRoute('app_stock_products_sticker', ['id' => $id]);
    }

    private function renderStickerPdf(
        ?StockProduct $product,
        ?StockProductVariant $variant,
        string $barcode,
        ?string $qrDataUri,
        ?string $stickerLogoUrl,
        string $filename,
    ): Response {
        $pdfTitleParts = array_values(array_filter([
            'Sticker',
            $product?->getName(),
            $variant?->getName(),
            $barcode !== '' ? $barcode : null,
        ], static fn ($v): bool => is_string($v) && trim($v) !== ''));
        $pdfTitle = implode(' - ', $pdfTitleParts);

        $html = $this->renderView('stock/products/variant_sticker_pdf.html.twig', [
            'variant' => $variant,
            'product' => $product,
            'barcode' => $barcode,
            'qr_data_uri' => $qrDataUri,
            'logo_url' => $stickerLogoUrl !== null && trim($stickerLogoUrl) !== '' ? $stickerLogoUrl : null,
            'pdf_title' => $pdfTitle,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        // Force single-page sticker size: 90mm x 50mm
        $wPt = (90 / 25.4) * 72;
        $hPt = (50 / 25.4) * 72;
        $dompdf->setPaper([0, 0, $wPt, $hPt]);
        $dompdf->render();

        $pdf = $dompdf->output();

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function generateUniqueBarcode(
        EntityManagerInterface $entityManager,
        StockProductVariantRepository $stockProductVariantRepository,
    ): string {
        // Short, printable, case-insensitive code: 8 chars base32-ish
        // Example: 1762V3A9
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $maxAttempts = 25;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = '';
            $alphaLen = strlen($alphabet);
            for ($j = 0; $j < 8; $j++) {
                $code .= $alphabet[random_int(0, $alphaLen - 1)];
            }

            // uniqueness against both product.barcode and variant.barcode
            $existsInVariants = $stockProductVariantRepository->count(['barcode' => $code]) > 0;
            if ($existsInVariants) {
                continue;
            }
            $existsInProducts = (int) $entityManager->createQueryBuilder()
                ->select('COUNT(p.id)')
                ->from(StockProduct::class, 'p')
                ->where('p.barcode = :code')
                ->setParameter('code', $code)
                ->getQuery()
                ->getSingleScalarResult() > 0;
            if ($existsInProducts) {
                continue;
            }

            return $code;
        }

        // Fallback (should never happen)
        return strtoupper(bin2hex(random_bytes(4)));
    }

    // QR generation is handled by StockProductMediaManager.
}

