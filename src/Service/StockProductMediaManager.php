<?php

namespace App\Service;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class StockProductMediaManager
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%stock_products_upload_dir%')] private readonly string $photoUploadDir,
        #[Autowire('%stock_products_qr_upload_dir%')] private readonly string $qrUploadDir,
    ) {}

    /**
     * Upload a product photo and returns a relative public path (e.g. uploads/stock-products/xxx.jpg).
     */
    public function uploadProductPhoto(UploadedFile $photo): string
    {
        if (!is_dir($this->photoUploadDir) && !@mkdir($this->photoUploadDir, 0775, true) && !is_dir($this->photoUploadDir)) {
            throw new \RuntimeException('Impossible de créer le dossier de stockage des images.');
        }

        $ext = $photo->guessExtension() ?: 'bin';
        $base = pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME) ?: 'product';
        $base = preg_replace('/[^a-zA-Z0-9]+/', '-', $base) ?? 'product';
        $base = trim(strtolower($base), '-');
        if ($base === '') {
            $base = 'product';
        }

        $rand = bin2hex(random_bytes(8));
        $filename = sprintf('%s-%s.%s', $base, $rand, $ext);
        $photo->move($this->photoUploadDir, $filename);

        return 'uploads/stock-products/' . $filename;
    }

    /**
     * Generate a new QR code PNG and returns a relative public path (e.g. uploads/stock-products-qr/qr-....png).
     */
    public function generateProductQrPng(string $payload): string
    {
        if (!is_dir($this->qrUploadDir) && !@mkdir($this->qrUploadDir, 0775, true) && !is_dir($this->qrUploadDir)) {
            throw new \RuntimeException('Impossible de créer le dossier de stockage des QR codes.');
        }

        $rand = bin2hex(random_bytes(12));
        $filename = sprintf('qr-product-%s.png', $rand);
        $absolutePath = rtrim($this->qrUploadDir, "\\/") . DIRECTORY_SEPARATOR . $filename;

        $qrCode = new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 420,
            margin: 0,
        );

        $png = (new PngWriter())->write($qrCode)->getString();
        $bytes = @file_put_contents($absolutePath, $png);
        if (!is_int($bytes) || $bytes <= 0) {
            throw new \RuntimeException('Impossible d’enregistrer le QR code sur le disque.');
        }

        return 'uploads/stock-products-qr/' . $filename;
    }

    /**
     * Deletes a previously stored relative path, but only if it is safe and within expected folders.
     */
    public function deletePublicFileSafely(?string $relativePath): void
    {
        $relativePath = $relativePath !== null ? trim($relativePath) : null;
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $rel = str_replace('\\', '/', $relativePath);
        $rel = ltrim($rel, '/');

        // Never allow traversal, absolute paths or URLs.
        if (str_contains($rel, '..') || preg_match('~^[a-zA-Z]:/~', $rel) === 1 || str_contains($rel, '://')) {
            return;
        }

        $allowedPrefixes = [
            'uploads/stock-products/',
            'uploads/stock-products-qr/',
        ];
        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($rel, $prefix)) {
                $isAllowed = true;
                break;
            }
        }
        if (!$isAllowed) {
            return;
        }

        $abs = $this->projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}

