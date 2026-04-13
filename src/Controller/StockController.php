<?php

namespace App\Controller;

use App\Entity\StockProduct;
use App\Entity\StockProductVariant;
use App\Repository\StockProductRepository;
use App\Repository\StockProductVariantRepository;
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
        #[Autowire('%stock_products_upload_dir%')] string $stock_products_upload_dir,
        #[Autowire('%stock_products_qr_upload_dir%')] string $stock_products_qr_upload_dir,
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

        // QR code is generated at creation time (mandatory)
        $barcodeForQr = $product->getBarcode();
        if ($barcodeForQr !== null) {
            $qrPath = $this->generateQrPngPath($barcodeForQr, $stock_products_qr_upload_dir);
            if ($qrPath !== '') {
                $product->setQrCodePath($qrPath);
            }
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

    #[Route('/produits/{id}/edit', name: 'app_stock_products_edit', methods: ['GET'])]
    public function productsEdit(int $id, StockProductRepository $stockProductRepository): Response
    {
        $product = $stockProductRepository->find($id);
        if (!$product instanceof StockProduct) {
            throw $this->createNotFoundException('Produit introuvable.');
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
        #[Autowire('%stock_products_upload_dir%')] string $stock_products_upload_dir,
    ): Response {
        $product = $stockProductRepository->find($id);
        if (!$product instanceof StockProduct) {
            throw $this->createNotFoundException('Produit introuvable.');
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

            if (!is_dir($stock_products_upload_dir) && !@mkdir($stock_products_upload_dir, 0775, true) && !is_dir($stock_products_upload_dir)) {
                $this->addFlash('error', 'Impossible de créer le dossier de stockage des images.');

                return $this->redirectToRoute('app_stock_products_edit', ['id' => $product->getId()]);
            }

            $ext = $photo->guessExtension() ?: 'bin';
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

        $entityManager->flush();
        $this->addFlash('success', 'Produit modifié avec succès.');

        return $this->redirectToRoute('app_stock_products_index');
    }

    #[Route('/produits/{id}/delete', name: 'app_stock_products_delete', methods: ['POST'])]
    public function productsDelete(
        int $id,
        Request $request,
        StockProductRepository $stockProductRepository,
        EntityManagerInterface $entityManager,
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

        $entityManager->remove($product);
        $entityManager->flush();

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

    private function generateQrPngPath(string $barcode, string $qrUploadDir): string
    {
        if (!is_dir($qrUploadDir) && !@mkdir($qrUploadDir, 0775, true) && !is_dir($qrUploadDir)) {
            // If the folder can't be created, keep the app functional.
            return '';
        }

        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $barcode) ?? 'qr';
        $safe = trim($safe, '-');
        if ($safe === '') {
            $safe = 'qr';
        }
        $filename = sprintf('product-%s.png', $safe);
        $absolutePath = rtrim($qrUploadDir, "\\/") . DIRECTORY_SEPARATOR . $filename;

        $qrCode = new QrCode(
            data: $barcode,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 400,
            margin: 0,
        );

        $png = (new PngWriter())->write($qrCode)->getString();
        @file_put_contents($absolutePath, $png);

        return 'uploads/stock-products-qr/' . $filename;
    }
}

