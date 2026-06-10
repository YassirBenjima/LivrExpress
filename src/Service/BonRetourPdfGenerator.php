<?php

namespace App\Service;

use App\Entity\ReturnRequest;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class BonRetourPdfGenerator
{
    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function generateDownloadResponse(ReturnRequest $demande): Response
    {
        $reference = trim((string) $demande->getBonReference());
        if ($reference === '') {
            throw new \RuntimeException('Document non disponible');
        }

        $statusLabel = ReturnRequest::getStatusLabels()[$demande->getStatus()] ?? $demande->getStatus();
        $logoDataUri = $this->resolveLogoDataUri();

        $html = $this->twig->render('retour/bons/pdf.html.twig', [
            'demande' => $demande,
            'status_label' => $statusLabel,
            'logo_data_uri' => $logoDataUri,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = $this->buildFilename($reference);

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function buildFilename(string $reference): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', $reference) ?? 'bon';
        $safe = trim($safe, '-');

        return ($safe !== '' ? $safe : 'bon-retour') . '.pdf';
    }

    private function resolveLogoDataUri(): ?string
    {
        $logoPath = $this->projectDir . '/public/assets/media/app/default-logo.svg';
        if (!is_file($logoPath)) {
            return null;
        }

        $contents = file_get_contents($logoPath);
        if ($contents === false) {
            return null;
        }

        return 'data:image/svg+xml;base64,' . base64_encode($contents);
    }
}
