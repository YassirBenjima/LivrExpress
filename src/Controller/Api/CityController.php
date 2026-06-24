<?php

namespace App\Controller\Api;

use App\Repository\CityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/cities')]
#[IsGranted('ROLE_USER')]
class CityController extends AbstractController
{
    #[Route('', name: 'api_cities_list', methods: ['GET'])]
    public function list(CityRepository $cityRepository): JsonResponse
    {
        $cities = [];
        foreach ($cityRepository->findBy([], ['name' => 'ASC']) as $city) {
            $name = $city->getName();
            if ($name !== null && $name !== '') {
                $cities[] = $name;
            }
        }

        return $this->json($cities);
    }
}
