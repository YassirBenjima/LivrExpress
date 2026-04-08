<?php

namespace App\Controller;

use App\Entity\Colis;
use App\Entity\User;
use App\Form\ColisType;
use App\Repository\CityRepository;
use App\Repository\ColisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/colis')]
final class ColisController extends AbstractController
{
    #[Route('', name: 'app_colis_index', methods: ['GET'])]
    public function index(ColisRepository $colisRepository): Response
    {
        return $this->render('colis/index.html.twig', [
            'colis_list' => $colisRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_colis_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CityRepository $cityRepository, ColisRepository $colisRepository): Response
    {
        $colis = new Colis();
        $cityChoices = [];
        foreach ($cityRepository->findBy([], ['name' => 'ASC']) as $city) {
            $cityName = (string) $city->getName();
            $cityChoices[$cityName] = $cityName;
        }
        $oldColisChoices = [];
        foreach ($colisRepository->findBy([], ['id' => 'DESC']) as $oldColis) {
            $orderNumber = (string) $oldColis->getOrderNumber();
            $oldColisChoices[$orderNumber] = $orderNumber;
        }
        $user = $this->getUser();
        $defaultPackageOption = $user instanceof User ? $user->getPackageOption() : null;
        if (!$defaultPackageOption) {
            $defaultPackageOption = 'Ne pas ouvrir le colis';
        }

        $form = $this->createForm(ColisType::class, $colis, [
            'city_choices' => $cityChoices,
            'old_colis_choices' => $oldColisChoices,
            'default_package_option' => $defaultPackageOption,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($colis);
            $entityManager->flush();

            $this->addFlash('success', 'Colis ajoute avec succes.');

            return $this->redirectToRoute('app_colis_index');
        }

        return $this->render('colis/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/pickup', name: 'app_colis_pickup', methods: ['GET'])]
    public function pickup(ColisRepository $colisRepository): Response
    {
        return $this->render('colis/pickup.html.twig', [
            'colis_list' => $colisRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/import', name: 'app_colis_import', methods: ['GET'])]
    public function import(): Response
    {
        return $this->render('colis/import.html.twig');
    }

    #[Route('/settings', name: 'app_colis_settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('colis/settings.html.twig');
    }
}
