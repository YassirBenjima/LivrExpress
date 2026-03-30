<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(\App\Repository\CityRepository $cityRepository): Response
    {
        $cities = $cityRepository->findBy([], ['name' => 'ASC']);

        return $this->render('registration/register.html.twig', [
            'cities' => $cities,
        ]);
    }
}
