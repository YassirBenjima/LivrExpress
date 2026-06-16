<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/affiliate')]
final class AffiliateController extends AbstractController
{
    #[Route('', name: 'app_affiliate_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userId = (string) $user->getId();

        $fullLink = $this->generateUrl(
            'app_register',
            ['ref' => $userId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $shortLink = rtrim($request->getSchemeAndHttpHost(), '/') . '/r/' . $userId;

        return $this->render('affiliate/index.html.twig', [
            'next_payment' => 0,
            'total_referred' => 0,
            'total_earnings' => 0,
            'full_link' => $fullLink,
            'short_link' => $shortLink,
        ]);
    }
}
