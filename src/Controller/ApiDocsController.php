<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/api-docs')]
final class ApiDocsController extends AbstractController
{
    #[Route('', name: 'app_api_docs', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        return $this->render('api_docs/index.html.twig', [
            'api_key' => $user->getApiKey(),
        ]);
    }

    #[Route('/generate-key', name: 'app_api_docs_generate', methods: ['POST'])]
    public function generateKey(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $newKey = bin2hex(random_bytes(16));
        $user->setApiKey($newKey);
        $entityManager->flush();

        $this->addFlash('success', 'Votre nouvelle clé API a été générée avec succès.');

        return $this->redirectToRoute('app_api_docs');
    }
}
