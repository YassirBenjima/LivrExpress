<?php

namespace App\Controller;

use App\Entity\StockProduct;
use App\Entity\StockProductVariant;
use App\Repository\StockProductRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    #[Route('/produits', name: 'app_stock_products_index', methods: ['GET'])]
    public function productsIndex(Request $request, StockProductRepository $stockProductRepository): Response
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
                        'name' => $variant->getName(),
                        'ref_or_barcode' => $variant->getBarcode() ?: null,
                        'qty_received' => 0,
                        'qty_not_received' => $variant->getQuantity(),
                    ];
                }
            }

            $products[] = [
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

        $totalProducts = \count($products);
        $totalQty = array_sum(array_map(
            static fn (array $p): int => (int) ($p['qty_received'] ?? 0) + (int) ($p['qty_not_received'] ?? 0),
            $products
        ));

        return $this->render('stock/products/index.html.twig', [
            'products' => $products,
            'search_query' => $search,
            'total_products' => $totalProducts,
            'total_qty' => $totalQty,
        ]);
    }

    #[Route('/produits/new', name: 'app_stock_products_new', methods: ['GET'])]
    public function productsNew(): Response
    {
        return $this->render('stock/products/new.html.twig');
    }

    #[Route('/produits', name: 'app_stock_products_create', methods: ['POST'])]
    public function productsCreate(
        Request $request,
        EntityManagerInterface $entityManager,
        #[Autowire('%stock_products_upload_dir%')] string $stock_products_upload_dir
    ): Response
    {
        if (!$this->isCsrfTokenValid('stock_product_new', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');

            return $this->redirectToRoute('app_stock_products_new');
        }

        $name = trim((string) $request->request->get('name', ''));
        $category = trim((string) $request->request->get('category', ''));
        $variantsEnabled = (bool) $request->request->get('variants_enabled');

        $allowedCategories = ['generale', 'cosmetique', 'electronique', 'accessoire'];
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
                $product->addVariant($variant);
            }

            if (!$hasAtLeastOne) {
                $this->addFlash('error', 'Ajoutez au moins une variante ou désactivez le mode variantes.');

                return $this->redirectToRoute('app_stock_products_new');
            }
        } else {
            $product->setBarcode($request->request->get('barcode'));

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

            if (!is_dir($stock_products_upload_dir) && !@mkdir($stock_products_upload_dir, 0775, true) && !is_dir($stock_products_upload_dir)) {
                $this->addFlash('error', 'Impossible de créer le dossier de stockage des images.');

                return $this->redirectToRoute('app_stock_products_new');
            }

            $ext = $photo->guessExtension() ?: 'bin';
            // Same naming format as user avatars: <safe-base>-<uniqid>.<ext>
            // Keep it consistent for the whole app, and avoid collisions.
            $base = pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME) ?: 'product';
            $base = preg_replace('/[^a-zA-Z0-9]+/', '-', $base) ?? 'product';
            $base = trim(strtolower($base), '-');
            if ($base === '') {
                $base = 'product';
            }
            $filename = sprintf('%s-%s.%s', $base, uniqid(), $ext);

            $photo->move($stock_products_upload_dir, $filename);
            $product->setPhotoPath('uploads/stock-products/' . $filename);
        }

        $entityManager->persist($product);
        $entityManager->flush();

        $this->addFlash('success', 'Produit enregistré avec succès.');

        return $this->redirectToRoute('app_stock_products_index');
    }
}

